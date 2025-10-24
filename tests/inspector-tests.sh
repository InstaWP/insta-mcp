#!/bin/bash

# MCP Inspector Integration Tests
# Tests all WordPress MCP tools via HTTP using the MCP inspector

set -e  # Exit on error

# Default URL - can be overridden with environment variable
SERVER_URL="${MCP_SERVER_URL:-https://mcp-php.instawp.site/insta-mcp}"
TOKEN="${MCP_TOKEN:-cfccb94a66cfb16a7e82b2b5f6f9221fdb44e052b230156602f916e4bb5c693e}"

# Use query parameter for token (works with all HTTP methods)
if [ -n "$TOKEN" ]; then
    echo "Using token authentication via query parameter"
    SERVER_URL_WITH_TOKEN="${SERVER_URL}?t=${TOKEN}"
    INSPECTOR="npx @modelcontextprotocol/inspector --cli $SERVER_URL_WITH_TOKEN --transport http"
else
    echo "No authentication"
    INSPECTOR="npx @modelcontextprotocol/inspector --cli $SERVER_URL --transport http"
fi

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

TESTS_PASSED=0
TESTS_FAILED=0

echo "========================================="
echo "MCP WordPress Inspector Integration Tests"
echo "========================================="
echo ""

# Helper function to run a test
run_test() {
    local test_name=$1
    local command=$2

    echo -n "Testing: $test_name... "

    # Run command and save output, then check with grep
    local inspector_cmd="${command% | grep*}"  # Remove grep part
    local grep_pattern="${command##*grep -q }"  # Extract grep pattern
    grep_pattern="${grep_pattern//\'/}"  # Remove quotes

    if eval "$inspector_cmd" > /tmp/mcp_test_output 2>&1 && grep -q "$grep_pattern" /tmp/mcp_test_output; then
        echo -e "${GREEN}✓ PASSED${NC}"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC}"
        echo "Output:"
        cat /tmp/mcp_test_output
        echo ""
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

# Test 1: List all tools
run_test "List all tools" \
    "$INSPECTOR --method tools/list | grep -q 'list_content'"

# Test 2: Discover content types
run_test "Discover content types" \
    "$INSPECTOR --method tools/call --tool-name discover_content_types | grep -q 'content_types'"

# Test 3: List content (posts)
run_test "List content (posts)" \
    "$INSPECTOR --method tools/call --tool-name list_content --tool-arg content_type=post | grep -q 'content_type.*post'"

# Test 4: List content (pages)
run_test "List content (pages)" \
    "$INSPECTOR --method tools/call --tool-name list_content --tool-arg content_type=page | grep -q 'content_type.*page'"

# Test 5: Get content by ID
run_test "Get content by ID" \
    "$INSPECTOR --method tools/call --tool-name get_content --tool-arg content_id=1 | grep -q 'id.*1'"

# Test 6: Create draft post
run_test "Create draft post" \
    "$INSPECTOR --method tools/call --tool-name create_content --tool-arg content_type=post --tool-arg title='Inspector Test Post' --tool-arg content='Test content from inspector tests' --tool-arg status=draft | grep -q 'status.*draft'"

# Get the ID of the created post for further tests
CREATED_POST_ID=$(cat /tmp/mcp_test_output | grep -o 'id[^0-9]*[0-9][0-9]*' | head -1 | grep -o '[0-9][0-9]*')

if [ -n "$CREATED_POST_ID" ]; then
    # Test 7: Update the created post
    run_test "Update post status to publish" \
        "$INSPECTOR --method tools/call --tool-name update_content --tool-arg content_id=$CREATED_POST_ID --tool-arg status=publish | grep -q 'status.*publish'"

    # Test 8: Get the updated post
    run_test "Get updated post" \
        "$INSPECTOR --method tools/call --tool-name get_content --tool-arg content_id=$CREATED_POST_ID | grep -q 'status.*publish'"

    # Test 9: Delete the post (soft delete)
    run_test "Soft delete post" \
        "$INSPECTOR --method tools/call --tool-name delete_content --tool-arg content_id=$CREATED_POST_ID | grep -q 'permanently_deleted.*false'"

    # Test 10: Permanently delete the post
    run_test "Permanently delete post" \
        "$INSPECTOR --method tools/call --tool-name delete_content --tool-arg content_id=$CREATED_POST_ID --tool-arg force_delete=true | grep -q 'permanently_deleted.*true'"
else
    echo -e "${YELLOW}⚠ Skipping update/delete tests (no post ID)${NC}"
fi

# Test 11: Discover taxonomies
run_test "Discover taxonomies" \
    "$INSPECTOR --method tools/call --tool-name discover_taxonomies | grep -q 'taxonomies'"

# Test 12: Get taxonomy (category)
run_test "Get category taxonomy" \
    "$INSPECTOR --method tools/call --tool-name get_taxonomy --tool-arg taxonomy=category | grep -q 'name.*category'"

# Test 13: List terms (categories)
run_test "List category terms" \
    "$INSPECTOR --method tools/call --tool-name list_terms --tool-arg taxonomy=category | grep -q 'taxonomy.*category'"

# Test 14: Create a term
run_test "Create category term" \
    "$INSPECTOR --method tools/call --tool-name create_term --tool-arg taxonomy=category --tool-arg name='Inspector Test Category' | grep -q 'Inspector Test Category'"

# Get the ID of the created term
CREATED_TERM_ID=$(cat /tmp/mcp_test_output | grep -o 'id[^0-9]*[0-9][0-9]*' | head -1 | grep -o '[0-9][0-9]*')

if [ -n "$CREATED_TERM_ID" ]; then
    # Test 15: Get the created term
    run_test "Get created term" \
        "$INSPECTOR --method tools/call --tool-name get_term --tool-arg taxonomy=category --tool-arg term_id=$CREATED_TERM_ID | grep -q 'Inspector Test Category'"

    # Test 16: Update the term
    run_test "Update term" \
        "$INSPECTOR --method tools/call --tool-name update_term --tool-arg taxonomy=category --tool-arg term_id=$CREATED_TERM_ID --tool-arg name='Updated Inspector Category' | grep -q 'Updated Inspector Category'"

    # Test 17: Delete the term
    run_test "Delete term" \
        "$INSPECTOR --method tools/call --tool-name delete_term --tool-arg taxonomy=category --tool-arg term_id=$CREATED_TERM_ID | grep -q 'Updated Inspector Category'"
else
    echo -e "${YELLOW}⚠ Skipping term update/delete tests (no term ID)${NC}"
fi

# Test 18: Get content by slug
run_test "Get content by slug" \
    "$INSPECTOR --method tools/call --tool-name get_content_by_slug --tool-arg slug=hello-world | grep -q 'slug.*hello-world'"

# Plugin & Theme Management Tests

# Test 19: Search for plugins
run_test "Search for plugins" \
    "$INSPECTOR --method tools/call --tool-name plugin_info --tool-arg action=search --tool-arg query=seo | grep -q 'plugins'"

# Test 20: List installed plugins
run_test "List installed plugins" \
    "$INSPECTOR --method tools/call --tool-name plugin_info --tool-arg action=list | grep -q 'plugins'"

# Test 21: Get plugin info
run_test "Get plugin info (hello-dolly)" \
    "$INSPECTOR --method tools/call --tool-name plugin_info --tool-arg action=get --tool-arg plugin=hello-dolly | grep -q 'Hello Dolly'"

# Test 22: Install a small test plugin
run_test "Install plugin (hello-dolly)" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=install --tool-arg plugin=hello-dolly --tool-arg source=wordpress.org | grep -q 'installed'"

# Test 23: Activate the installed plugin
run_test "Activate plugin (hello-dolly)" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=activate --tool-arg plugin=hello-dolly | grep -q 'activated'"

# Test 24: Deactivate the plugin
run_test "Deactivate plugin (hello-dolly)" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=deactivate --tool-arg plugin=hello-dolly | grep -q 'deactivated'"

# Test 25: Read plugin file
run_test "Read plugin file" \
    "$INSPECTOR --method tools/call --tool-name plugin_files --tool-arg action=read --tool-arg plugin=hello-dolly --tool-arg file_path=hello.php | grep -q 'content'"

# Test 26: Search for themes
run_test "Search for themes" \
    "$INSPECTOR --method tools/call --tool-name theme_info --tool-arg action=search --tool-arg query=minimal | grep -q 'themes'"

# Test 27: List installed themes
run_test "List installed themes" \
    "$INSPECTOR --method tools/call --tool-name theme_info --tool-arg action=list | grep -q 'themes'"

# Test 28: Get current theme info
run_test "Get current theme info" \
    "$INSPECTOR --method tools/call --tool-name theme_info --tool-arg action=get --tool-arg theme=twentytwentyfour | grep -q 'Twenty'"

# Clean up
rm -f /tmp/mcp_test_output

# Summary
echo ""
echo "========================================="
echo "Test Summary"
echo "========================================="
echo -e "Total tests: $((TESTS_PASSED + TESTS_FAILED))"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed! ✗${NC}"
    exit 1
fi

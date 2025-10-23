<?php

namespace InstaWP\MCP\PHP\OAuth;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\Clock\SystemClock;

/**
 * JWT Service
 *
 * Handles creation and validation of JWT access tokens for OAuth 2.1
 */
class JwtService
{
    private Configuration $config;
    private string $issuer;
    private string $audience;

    /**
     * @param string $privateKeyPath Path to RSA private key
     * @param string $publicKeyPath Path to RSA public key
     * @param string $issuer OAuth issuer URL
     * @param string $audience Resource identifier (MCP server URL)
     */
    public function __construct(
        string $privateKeyPath,
        string $publicKeyPath,
        string $issuer,
        string $audience
    ) {
        $this->issuer = $issuer;
        $this->audience = $audience;

        // Configure JWT with RSA SHA-256 signing
        $this->config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file($privateKeyPath),
            InMemory::file($publicKeyPath)
        );
    }

    /**
     * Create a new JWT access token
     *
     * @param string $jti Unique token identifier (for revocation)
     * @param int $userId WordPress user ID
     * @param string $clientId OAuth client ID
     * @param array $scopes Array of granted scopes
     * @param int $ttl Token lifetime in seconds (default: 3600 = 1 hour)
     * @return string The encoded JWT token
     */
    public function createAccessToken(
        string $jti,
        int $userId,
        string $clientId,
        array $scopes,
        int $ttl = 3600
    ): string {
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify("+{$ttl} seconds");

        // Get WordPress user info
        $user = get_userdata($userId);
        $username = $user ? $user->user_login : '';
        $roles = $user ? $user->roles : [];

        // Build JWT token
        $token = $this->config->builder()
            // Standard claims
            ->issuedBy($this->issuer)                    // iss
            ->permittedFor($this->audience)              // aud
            ->identifiedBy($jti)                         // jti (for revocation)
            ->issuedAt($now)                             // iat
            ->canOnlyBeUsedAfter($now)                   // nbf
            ->expiresAt($expiresAt)                      // exp
            ->relatedTo((string) $userId)                // sub (user ID)

            // Custom claims
            ->withClaim('client_id', $clientId)
            ->withClaim('scope', implode(' ', $scopes))  // Space-separated scopes
            ->withClaim('username', $username)
            ->withClaim('roles', $roles)

            // Sign the token
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    /**
     * Validate and parse a JWT token
     *
     * @param string $tokenString The JWT token string
     * @return array{valid: bool, token: ?Token, error: ?string, claims: ?array}
     */
    public function validateToken(string $tokenString): array
    {
        try {
            // Parse the token
            $token = $this->config->parser()->parse($tokenString);
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'token' => null,
                'error' => 'Invalid token format: ' . $e->getMessage(),
                'claims' => null
            ];
        }

        // Validate signature and claims
        $constraints = [
            new SignedWith($this->config->signer(), $this->config->verificationKey()),
            new IssuedBy($this->issuer),
            new PermittedFor($this->audience),
            new StrictValidAt(SystemClock::fromSystemTimezone()),
        ];

        $validator = $this->config->validator();

        foreach ($constraints as $constraint) {
            if (!$validator->validate($token, $constraint)) {
                return [
                    'valid' => false,
                    'token' => $token,
                    'error' => 'Token validation failed: ' . get_class($constraint),
                    'claims' => null
                ];
            }
        }

        // Extract claims
        $claims = $token->claims();

        return [
            'valid' => true,
            'token' => $token,
            'error' => null,
            'claims' => [
                'jti' => $claims->get('jti'),
                'user_id' => (int) $claims->get('sub'),
                'client_id' => $claims->get('client_id'),
                'scopes' => explode(' ', $claims->get('scope', '')),
                'username' => $claims->get('username'),
                'roles' => $claims->get('roles', []),
                'expires_at' => $claims->get('exp')->getTimestamp(),
                'issued_at' => $claims->get('iat')->getTimestamp(),
            ]
        ];
    }

    /**
     * Get the public key in JWK format for the JWKS endpoint
     *
     * @return array JWK (JSON Web Key) representation
     */
    public function getPublicKeyJwk(): array
    {
        $publicKey = $this->config->verificationKey()->contents();

        // Parse the RSA public key
        $keyResource = openssl_pkey_get_public($publicKey);
        if ($keyResource === false) {
            throw new \RuntimeException('Failed to parse public key');
        }

        $keyDetails = openssl_pkey_get_details($keyResource);
        if ($keyDetails === false || !isset($keyDetails['rsa'])) {
            throw new \RuntimeException('Invalid RSA public key');
        }

        // Base64url encode the modulus and exponent
        $n = rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=');

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $n,
            'e' => $e,
        ];
    }

    /**
     * Get the full JWKS (JSON Web Key Set) for the .well-known/jwks.json endpoint
     *
     * @return array JWKS structure
     */
    public function getJwks(): array
    {
        return [
            'keys' => [
                $this->getPublicKeyJwk()
            ]
        ];
    }

    /**
     * Get the OAuth issuer URL
     *
     * @return string Issuer URL
     */
    public function getIssuer(): string
    {
        return $this->issuer;
    }
}

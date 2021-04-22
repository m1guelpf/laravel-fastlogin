<?php

namespace M1guelpf\FastLogin\Utils;

use Illuminate\Support\Str;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\PublicKeyCredentialSource;
use M1guelpf\FastLogin\Models\WebAuthnCredential;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialSourceRepository;

class CredentialSource implements PublicKeyCredentialSourceRepository
{
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = WebAuthnCredential::where('credId', $publicKeyCredentialId)->first();

        return is_null($credential) ? $credential : new PublicKeyCredentialSource(
            $credential['credId'], 'public-key', ['internal'], 'none', new EmptyTrustPath, Str::uuid(), $credential['key'], $credential->user_id, 0,
        );
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return []; // Not Implemented
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        // Not Implemented
    }
}

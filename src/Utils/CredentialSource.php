<?php

namespace M1guelpf\FastLogin\Utils;

use Illuminate\Support\Str;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\PublicKeyCredentialSource;
use M1guelpf\FastLogin\Models\Credential;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialSourceRepository;

class CredentialSource implements PublicKeyCredentialSourceRepository
{
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = Credential::where('credId', $publicKeyCredentialId)->first();

        return is_null($credential) ? $credential : new PublicKeyCredentialSource(
            publicKeyCredentialId: $credential['credId'],
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: new EmptyTrustPath,
            aaguid: Str::uuid(),
            credentialPublicKey: $credential['key'],
            userHandle: $credential->user_id,
            counter: 0,
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

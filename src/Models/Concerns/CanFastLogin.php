<?php

namespace M1guelpf\FastLogin\Models\Concerns;

use M1guelpf\FastLogin\Models\WebAuthnCredential;

trait CanFastLogin
{
    public function webauthnCredentials()
    {
        return $this->hasMany(WebAuthnCredential::class);
    }
}

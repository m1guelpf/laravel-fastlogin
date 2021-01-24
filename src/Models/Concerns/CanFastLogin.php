<?php

namespace M1guelpf\FastLogin\Models\Concerns;

use M1guelpf\FastLogin\Models\Credential;

trait CanFastLogin
{
    public function credentials()
    {
        return $this->hasMany(Credential::class);
    }
}

<?php

namespace M1guelpf\FastLogin\Http\Controllers;

use Cose\Algorithms;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use M1guelpf\FastLogin\FastLoginServiceProvider;
use Webauthn\PublicKeyCredentialDescriptor as Credential;
use Webauthn\PublicKeyCredentialRpEntity as RelyingParty;
use Webauthn\PublicKeyCredentialUserEntity as UserEntity;
use Webauthn\PublicKeyCredentialLoader as CredentialLoader;
use Webauthn\AuthenticatorAssertionResponse as LoginResponse;
use Webauthn\AuthenticatorSelectionCriteria as Authenticator;
use Webauthn\PublicKeyCredentialRequestOptions as LoginRequest;
use Psr\Http\Message\ServerRequestInterface as CredentialRequest;
use Webauthn\PublicKeyCredentialParameters as CredentialParameter;
use Webauthn\PublicKeyCredentialCreationOptions as CreationRequest;
use Webauthn\AuthenticatorAttestationResponse as RegistrationResponse;
use Webauthn\AuthenticatorAssertionResponseValidator as LoginValidator;
use Webauthn\AuthenticatorAttestationResponseValidator as RegistrationValidator;

class FastLoginController
{
    public function createDetails(Request $request)
    {
        return tap(CreationRequest::create(
            rp: new RelyingParty(config('app.name'), $request->getHttpHost()),
            user: new UserEntity(
                $request->user()->email,
                $request->user()->id,
                $request->user()->name,
            ),
            challenge: random_bytes(16),
            pubKeyCredParams: [
                new CredentialParameter(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, Algorithms::COSE_ALGORITHM_ES256),
                new CredentialParameter(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, Algorithms::COSE_ALGORITHM_RS256),
            ],
        )->setAuthenticatorSelection(new Authenticator('platform'))->excludeCredentials($request->user()->credentials->map(function ($credential) {
            return new Credential(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, $credential['credId'], ['internal']);
        })->toArray()), fn ($creationOptions) => Cache::put($this->getCacheKey(), $creationOptions->jsonSerialize(), now()->addMinutes(5)))->jsonSerialize();
    }

    public function create(Request $request, CredentialLoader $credentialLoader, RegistrationValidator $registrationValidator, CredentialRequest $credentialRequest)
    {
        $credentials     = $credentialLoader->loadArray($request->all())->getResponse();
        $creationOptions = CreationRequest::createFromArray(Cache::pull($this->getCacheKey()));

        if (! $creationOptions || ! $credentials instanceof RegistrationResponse) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422);
        }

        try {
            $response = $registrationValidator->check($credentials, $creationOptions, $credentialRequest, [$creationOptions->getRp()->getId()]);
        } catch (InvalidArgumentException $e) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422, $e);
        }

        $request->user()->credentials()->create([
            'credId' => $credId = $response->getPublicKeyCredentialId(),
            'key'    => $response->getCredentialPublicKey(),
        ]);

        cookie()->queue(FastLoginServiceProvider::FASTLOGIN_COOKIE, $credId, 1 * Carbon::DAYS_PER_YEAR * Carbon::HOURS_PER_DAY * Carbon::MINUTES_PER_HOUR);

        return response()->noContent();
    }

    public function loginDetails(Request $request)
    {
        return tap(
            LoginRequest::create(random_bytes(16))
            ->setRpId($request->getHttpHost())
            ->allowCredential(new Credential(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, $request->cookie(FastLoginServiceProvider::FASTLOGIN_COOKIE), ['internal'])),
            fn ($requestOptions) => Cache::put($this->getCacheKey(), $requestOptions->jsonSerialize(), now()->addMinutes(5))
        )->jsonSerialize();
    }

    public function login(Request $request, CredentialLoader $credentialLoader, LoginValidator $loginValidator, CredentialRequest $credentialRequest)
    {
        $credentials    = $credentialLoader->loadArray($request->all())->getResponse();
        $requestOptions = LoginRequest::createFromArray(Cache::pull($this->getCacheKey()));

        if (! $requestOptions || ! $credentials instanceof LoginResponse) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422);
        }

        try {
            $response = $loginValidator->check($request->cookie(FastLoginServiceProvider::FASTLOGIN_COOKIE), $credentials, $requestOptions, $credentialRequest, null, [$requestOptions->getRpId()]);
        } catch (InvalidArgumentException $e) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422, $e);
        }

        Auth::loginUsingId(intval($response->getUserHandle()));

        return response()->noContent();
    }

    protected function getCacheKey()
    {
        return 'fastlogin-request-'.sha1(request()->getHttpHost().session()->getId());
    }
}

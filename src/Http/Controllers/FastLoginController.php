<?php

namespace M1guelpf\FastLogin\Http\Controllers;

use Cose\Algorithms;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use M1guelpf\FastLogin\Events\FastLoginLogIn;
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
	/**
	 * @param  \Illuminate\Http\Request  $request
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function createDetails(Request $request)
    {
        return tap(CreationRequest::create(
            new RelyingParty(config('app.name'), $request->getHttpHost()),
            new UserEntity(
                $request->user()->email,
                $request->user()->id,
                $request->user()->name,
            ),
            random_bytes(16),
            [
                new CredentialParameter(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, Algorithms::COSE_ALGORITHM_ES256),
                new CredentialParameter(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, Algorithms::COSE_ALGORITHM_RS256),
            ],
        )->setAuthenticatorSelection(new Authenticator('platform'))->excludeCredentials($request->user()->webauthnCredentials->map(function ($credential) {
            return new Credential(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, $credential['credId'], ['internal']);
        })->toArray()), fn ($creationOptions) => Cache::put($this->getCacheKey(), $creationOptions->jsonSerialize(), now()->addMinutes(5)))->jsonSerialize();
    }

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Webauthn\PublicKeyCredentialLoader  $credentialLoader
	 * @param  \Webauthn\AuthenticatorAttestationResponseValidator  $registrationValidator
	 * @param  \Psr\Http\Message\ServerRequestInterface  $credentialRequest
	 *
	 * @return mixed
	 */
	public function create(Request $request, CredentialLoader $credentialLoader, RegistrationValidator $registrationValidator, CredentialRequest $credentialRequest)
    {
        $credentials     = $credentialLoader->loadArray($request->all())->getResponse();
        $creationOptions = CreationRequest::createFromArray(Cache::pull($this->getCacheKey()));

        if (!$creationOptions || !$credentials instanceof RegistrationResponse) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422);
        }

        try {
            $response = $registrationValidator->check($credentials, $creationOptions, $credentialRequest, [$creationOptions->getRp()->getId()]);
        } catch (InvalidArgumentException $e) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422, $e);
        }

        $request->user()->webauthnCredentials()->create([
            'credId' => $credId = $response->getPublicKeyCredentialId(),
            'key'    => $response->getCredentialPublicKey(),
        ]);

        cookie()->queue(FastLoginServiceProvider::FASTLOGIN_COOKIE, $credId, 1 * Carbon::DAYS_PER_YEAR * Carbon::HOURS_PER_DAY * Carbon::MINUTES_PER_HOUR);

        return response()->noContent();
    }

	/**
	 * @param  \Illuminate\Http\Request  $request
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loginDetails(Request $request)
    {
        return tap(
            LoginRequest::create(random_bytes(16))
                ->setRpId($request->getHttpHost())
                ->allowCredential(new Credential(Credential::CREDENTIAL_TYPE_PUBLIC_KEY, $request->cookie(FastLoginServiceProvider::FASTLOGIN_COOKIE), ['internal'])),
            fn ($requestOptions) => Cache::put($this->getCacheKey(), $requestOptions->jsonSerialize(), now()->addMinutes(5))
        )->jsonSerialize();
    }

	/**
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Webauthn\PublicKeyCredentialLoader  $credentialLoader
	 * @param  \Webauthn\AuthenticatorAssertionResponseValidator  $loginValidator
	 * @param  \Psr\Http\Message\ServerRequestInterface  $credentialRequest
	 *
	 * @return mixed
	 */
	public function login(Request $request, CredentialLoader $credentialLoader, LoginValidator $loginValidator, CredentialRequest $credentialRequest)
    {
        $credentials    = $credentialLoader->loadArray($request->all())->getResponse();
        $requestOptions = LoginRequest::createFromArray(Cache::pull($this->getCacheKey()));

        if (!$requestOptions || !$credentials instanceof LoginResponse) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422);
        }

        try {
            $response = $loginValidator->check($request->cookie(FastLoginServiceProvider::FASTLOGIN_COOKIE), $credentials, $requestOptions, $credentialRequest, null, [$requestOptions->getRpId()]);
        } catch (InvalidArgumentException $e) {
            throw new UnauthorizedException('FastLogin: Failed validating request', 422, $e);
        }

		$authenticatable = Auth::loginUsingId(intval($response->getUserHandle()));

        if ($authenticatable instanceof Authenticatable) {
        	// Dispatch event that we have logged in via FastLogin.
        	FastLoginLogIn::dispatch($authenticatable);
		}

        return response()->noContent();
    }

	/**
	 * @return string
	 */
	protected function getCacheKey()
    {
        return 'fastlogin-request-' . sha1(request()->getHttpHost() . request()->session()->token());
    }
}

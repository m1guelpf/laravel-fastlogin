<?php

namespace M1guelpf\FastLogin\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Auth\Authenticatable;

class FastLoginLogIn
{
	use Dispatchable, InteractswithSockets, SerializesModels;

	public $user;

	public function __construct(Authenticatable $user) {
		$this->user = $user;
	}
}

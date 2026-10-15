<?php

declare(strict_types=1);

use App\Broadcasting\GroupChannel;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', UserChannel::class);
Broadcast::channel('group', GroupChannel::class);

<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Reset password - StockPilot')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    /**
     * Send a password reset link to the given email address.
     */
    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->status = __($status);
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}

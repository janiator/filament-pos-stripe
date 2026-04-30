<?php

namespace App\Http\Controllers\PowerOffice;

use App\Http\Controllers\Controller;
use App\Services\PowerOffice\PowerOfficeOnboardingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PowerOfficeOnboardingRedirectController extends Controller
{
    public function __invoke(Request $request, PowerOfficeOnboardingService $onboarding): View
    {
        $token = $request->header('X-PowerOffice-Onboarding-Token')
            ?? $request->header('X-Onboarding-Token')
            ?? $request->query('token');
        $state = $request->query('state');

        if (! is_string($token) || $token === '' || ! is_string($state) || $state === '') {
            return view('poweroffice.onboarding-redirect', [
                'success' => false,
                'message' => __('Missing onboarding parameters. Open PowerOffice setup from POSitiv again.'),
            ]);
        }

        try {
            $onboarding->completeFromCallback([
                'state' => $state,
                'token' => $token,
            ]);
        } catch (\Throwable) {
            return view('poweroffice.onboarding-redirect', [
                'success' => false,
                'message' => __('Could not complete PowerOffice onboarding. Return to POSitiv and try again.'),
            ]);
        }

        return view('poweroffice.onboarding-redirect', [
            'success' => true,
            'message' => __('PowerOffice onboarding finished. You can close this window and return to POSitiv.'),
        ]);
    }
}

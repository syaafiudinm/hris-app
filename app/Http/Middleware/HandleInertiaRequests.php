<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = "app";

    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $employee = $user?->employee()->with("employmentType")->first();

        return [
            ...parent::share($request),
            "appName" => config("app.name"),
            "auth" => [
                "user" => $user
                    ? [
                        "id" => $user->id,
                        "name" => $user->name,
                        "email" => $user->email,
                        "role" => $user->role,
                        "isManagement" => $user->isManagement(),
                        "isSuperAdmin" => $user->isSuperAdmin(),
                    ]
                    : null,
                "employee" => $employee
                    ? [
                        "id" => $employee->id,
                        "nik" => $employee->nik,
                        "position" => $employee->position,
                        "employmentType" => $employee->employmentType?->name,
                        "category" => $employee->employmentType?->category,
                        "isLeaveEligible" => $employee->isLeaveEligible(),
                        "isBpjsEligible" => $employee->isBpjsEligible(),
                    ]
                    : null,
            ],
            "flash" => [
                "success" => fn () => $request->session()->get("success"),
                "error" => fn () => $request->session()->get("error"),
            ],
        ];
    }
}

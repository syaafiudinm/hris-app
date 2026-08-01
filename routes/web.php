<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmploymentTypeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MitraPayrollSchemaController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware("guest")->group(function () {
    Route::get("/login", [LoginController::class, "create"])->name("login");
    Route::post("/login", [LoginController::class, "store"]);
});

Route::post("/logout", [LoginController::class, "destroy"])
    ->middleware("auth")
    ->name("logout");

Route::middleware("auth")->group(function () {
    Route::redirect("/", "/dashboard");

    Route::get("/dashboard", [DashboardController::class, "index"])->name(
        "dashboard",
    );

    /*
     |----------------------------------------------------------------------
     | Self-service — semua role, dibatasi ke data miliknya sendiri.
     |----------------------------------------------------------------------
     */
    Route::get("/absensi-saya", [AttendanceController::class, "me"])->name(
        "attendance.me",
    );
    Route::post("/absensi-saya/clock-in", [
        AttendanceController::class,
        "clockIn",
    ])->name("attendance.clock-in");
    Route::post("/absensi-saya/clock-out", [
        AttendanceController::class,
        "clockOut",
    ])->name("attendance.clock-out");

    Route::get("/cuti-saya", [LeaveRequestController::class, "mine"])->name(
        "leaves.mine",
    );
    Route::post("/cuti-saya", [LeaveRequestController::class, "store"])->name(
        "leaves.store",
    );
    Route::delete("/cuti-saya/{leaveRequest}", [
        LeaveRequestController::class,
        "destroy",
    ])->name("leaves.destroy");

    Route::get("/slip-gaji-saya", [PayrollController::class, "mine"])->name(
        "payroll.mine",
    );
    Route::get("/slip-gaji/{payroll}/dokumen", [
        PayrollController::class,
        "document",
    ])->name("payroll.document");

    /*
     |----------------------------------------------------------------------
     | Manajemen — HR (super_admin) & Manager.
     |----------------------------------------------------------------------
     */
    Route::middleware("role:super_admin,manager")->group(function () {
        Route::get("/absensi", [AttendanceController::class, "index"])->name(
            "attendance.index",
        );
        Route::get("/cuti", [LeaveRequestController::class, "index"])->name(
            "leaves.index",
        );
        Route::patch("/cuti/{leaveRequest}", [
            LeaveRequestController::class,
            "decide",
        ])->name("leaves.decide");
    });

    /*
     |----------------------------------------------------------------------
     | Konfigurasi & payroll — khusus Super Admin / HR.
     |----------------------------------------------------------------------
     */
    Route::middleware("role:super_admin")->group(function () {
        Route::resource("employees", EmployeeController::class)->parameters([
            "employees" => "employee",
        ]);

        Route::get("/entitas-kerja", [
            EmploymentTypeController::class,
            "index",
        ])->name("employment-types.index");
        Route::post("/entitas-kerja", [
            EmploymentTypeController::class,
            "store",
        ])->name("employment-types.store");
        Route::patch("/entitas-kerja/{employmentType}", [
            EmploymentTypeController::class,
            "update",
        ])->name("employment-types.update");
        Route::delete("/entitas-kerja/{employmentType}", [
            EmploymentTypeController::class,
            "destroy",
        ])->name("employment-types.destroy");

        Route::get("/payroll", [PayrollController::class, "index"])->name(
            "payroll.index",
        );
        Route::post("/payroll/run", [PayrollController::class, "run"])->name(
            "payroll.run",
        );
        Route::get("/payroll/{payroll}", [
            PayrollController::class,
            "show",
        ])->name("payroll.show");
        Route::patch("/payroll/{payroll}/status", [
            PayrollController::class,
            "updateStatus",
        ])->name("payroll.status");

        Route::get("/skema-mitra", [
            MitraPayrollSchemaController::class,
            "index",
        ])->name("mitra-schemas.index");
        Route::post("/skema-mitra/{employee}", [
            MitraPayrollSchemaController::class,
            "store",
        ])->name("mitra-schemas.store");
        Route::delete("/skema-mitra/{mitraPayrollSchema}", [
            MitraPayrollSchemaController::class,
            "destroy",
        ])->name("mitra-schemas.destroy");
    });

    /*
     |----------------------------------------------------------------------
     | Exporting Engine — akses dikontrol RBAC, setiap unduhan diaudit.
     |----------------------------------------------------------------------
     */
    Route::prefix("export")
        ->name("export.")
        ->group(function () {
            Route::middleware("role:super_admin,manager")->group(function () {
                Route::get("/absensi", [
                    AttendanceController::class,
                    "export",
                ])->name("attendance");
                Route::get("/keterlambatan", [
                    AttendanceController::class,
                    "exportLate",
                ])->name("attendance-late");
                Route::get("/timesheet-mitra", [
                    AttendanceController::class,
                    "exportMitraTimesheet",
                ])->name("mitra-timesheet");
                Route::get("/cuti", [
                    LeaveRequestController::class,
                    "export",
                ])->name("leaves");
            });

            Route::middleware("role:super_admin")->group(function () {
                Route::get("/tenaga-kerja", [
                    EmployeeController::class,
                    "export",
                ])->name("employees");
                Route::get("/kontrak-expiring", [
                    EmployeeController::class,
                    "exportExpiring",
                ])->name("employees-expiring");
                Route::get("/payroll", [
                    PayrollController::class,
                    "export",
                ])->name("payroll");
                Route::get("/payroll-bank", [
                    PayrollController::class,
                    "exportBankTransfer",
                ])->name("payroll-bank");
                Route::get("/payroll-pajak", [
                    PayrollController::class,
                    "exportTax",
                ])->name("payroll-tax");
            });
        });
});

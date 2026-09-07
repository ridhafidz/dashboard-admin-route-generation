<?php

namespace App\Http\Controllers\Api;

use App\Enums\DriverStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DriverStatusController extends Controller
{
    public function checkIn(Request $request)
    {
        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Akun ini tidak terhubung ke data driver'], 404);
        }

        if ($driver->status === DriverStatus::InDelivery) {
            return response()->json(['message' => 'Driver sedang menjalankan delivery'], 409);
        }

        $driver->update(['status' => DriverStatus::Active]);

        return response()->json([
            'message' => 'Check-in berhasil. Driver berstatus Active.',
            'status' => $driver->fresh()->status,
        ]);
    }

    public function ready(Request $request)
    {
        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Akun ini tidak terhubung ke data driver'], 404);
        }

        if ($driver->status === DriverStatus::Inactive) {
            return response()->json([
                'message' => 'Driver harus check-in sebelum menyatakan Ready.',
            ], 409);
        }

        if ($driver->status === DriverStatus::InDelivery) {
            return response()->json([
                'message' => 'Driver sedang menjalankan delivery.',
            ], 409);
        }

        $driver->update(['status' => DriverStatus::Ready]);

        return response()->json([
            'message' => 'Driver siap menerima route.',
            'status' => $driver->fresh()->status,
        ]);
    }

    public function checkOut(Request $request)
    {
        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Akun ini tidak terhubung ke data driver'], 404);
        }

        if ($driver->status === DriverStatus::InDelivery) {
            return response()->json([
                'message' => 'Route masih berjalan. Selesaikan route sebelum check-out.',
            ], 409);
        }

        $driver->update(['status' => DriverStatus::Inactive]);

        return response()->json([
            'message' => 'Check-out berhasil.',
            'status' => $driver->fresh()->status,
        ]);
    }
}

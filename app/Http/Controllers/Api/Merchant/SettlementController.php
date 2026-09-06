<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\CommissionSettlement;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    /**
     * List every settlement (payout) ever made to the logged-in
     * merchant, most recent first — used for the app's Settlement
     * History screen.
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function list(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $settlements = CommissionSettlement::where('user_id', $user->id)
                ->latest('paid_at')
                ->get();

            return response()->json([
                'status' => true,
                'data' => ['settlements' => $settlements],
                'message' => 'Successfully retrieved settlement history.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Full detail for one settlement — every transaction (order) it
     * covers, each itemized deduction, and the bank/transfer
     * reference. This is what the app builds the receipt screen and
     * downloadable PDF from.
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function detail(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $request->validate([
                'hashslug' => 'required|string',
            ]);

            // Scoped to this user's own settlements — a merchant must
            // never be able to view another merchant's payout receipt
            // by guessing/enumerating a hashslug.
            $settlement = CommissionSettlement::where('user_id', $user->id)
                ->where('hashslug', $request->hashslug)
                ->with(['deductions', 'transactions.order'])
                ->first();

            if (!$settlement) {
                return response()->json([
                    'status' => false,
                    'message' => 'Settlement not found.',
                ]);
            }

            return response()->json([
                'status' => true,
                'data' => ['settlement' => $settlement],
                'message' => 'Successfully retrieved settlement detail.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\State;
use App\Models\WaitingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CoverageController extends Controller
{
    /**
     * Checks whether a pickup location is inside the service area.
     * Accepts either an existing address_id, or a raw city + state_id
     * pair (for checking before the address is even saved). A location
     * is covered if either its own city is individually flagged
     * covered, OR the whole state it's in is flagged covered — state
     * coverage always wins regardless of the city's own flag, so admin
     * can cover an entire state in one toggle instead of enumerating
     * every city in it.
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function check(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);
            }

            $validate = Validator::make($request->all(), [
                'address_id' => 'required_without_all:city,state_id',
                'city' => 'required_without:address_id',
                'state_id' => 'required_without:address_id',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            if ($request->filled('address_id')) {
                $address = Address::find($request->address_id);
                if (!$address) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Address not found.',
                    ]);
                }
                $cityName = $address->city;
                $stateId = $address->state_id;
            } else {
                $cityName = $request->city;
                $stateId = $request->state_id;
            }

            $stateCovered = $stateId
                ? State::where('id', $stateId)->serviceCovered()->exists()
                : false;

            $cityCovered = $cityName
                ? City::where('name', $cityName)
                    ->when($stateId, fn ($q) => $q->where('state_id', $stateId))
                    ->serviceCovered()
                    ->exists()
                : false;

            $covered = $stateCovered || $cityCovered;

            return response()->json([
                'status' => true,
                'message' => 'Successfully checked coverage.',
                'data' => ['covered' => $covered],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Adds a person to the service-expansion waiting list — shown when
     * their pickup location isn't covered yet, as an alternative to
     * entering a different address.
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function joinWaitingList(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'name' => 'required|string|max:250',
                'email' => 'required|email|max:250',
                'phone' => 'required|string|max:20',
                'postcode' => 'required|string|max:20',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            // updateOrCreate on email — the admin's own WaitingListResource
            // already de-dupes by grouping on name/mobile_no/postcode, so
            // repeated sign-ups from the same person collapse into one
            // record here too, rather than piling up duplicates every
            // time they retry booking from an uncovered address.
            $waitingList = WaitingList::updateOrCreate(
                ['email' => $request->email],
                [
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'postcode' => $request->postcode,
                    'status' => WaitingList::ACTIVE,
                ]
            );

            return response()->json([
                'status' => true,
                'message' => "You're on the list! We'll notify you as soon as we launch in your area.",
                'data' => ['waiting_list' => $waitingList],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}

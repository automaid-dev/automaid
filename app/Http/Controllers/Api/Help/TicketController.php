<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Mail\NewSupportTicketEmail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class TicketController extends Controller
{
    /**
     * [index description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function index(Request $request)
    {
        try {
            $tickets = Ticket::where('user_id', auth()->user()->id)->latest()->get();
            $tickets->load(['order', 'user']);
            $data['tickets'] = $tickets;
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Tickets successfully retrieved.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [detail description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function detail(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'ticket_id' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }
            $ticket = Ticket::find($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Record not found.',
                ]);
            }

            $ticket->load(['order', 'user', 'replies']);
            $data['ticket'] = $ticket;            
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Ticket successfully retrieved.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [store description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function store(Request $request)
    {
        try {
            // check user
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            $validate = Validator::make($request->all(), [
                // 'order_id' => 'required',
                'issue_type' => 'required',
                'issue' => 'required',
                'image' => 'image|mimes:jpg,png|max:5120',
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            $image = null;
            if ($request->image) {
                $image = $this->uploadFile($request->image);
            }

            // get user type
            if (auth()->user()->roles->pluck('name')[0] == 'customer') {
                $user_type = 'customer';
            }
            if (auth()->user()->roles->pluck('name')[0] == 'rider') {
                $user_type = 'rider';
            }
            if (auth()->user()->roles->pluck('name')[0] == 'merchant') {
                $user_type = 'merchant';
            }

            // save ticket
            $ticket = new Ticket();
            $ticket->series_no = $ticket->getNextSeriesNo();
            $ticket->user_id = auth()->user()->id;
            $ticket->order_id = $request->order_id ?? null;
            $ticket->issue_type = $request->issue_type ?? null;
            $ticket->issue = $request->issue ?? null;
            $ticket->user_type = $user_type;
            $ticket->image = $image;
            $ticket->status = Ticket::OPEN;
            $ticket->save();

            // send email to admin
            if (User::superAdminEmail()) {
                $subject = 'Auto Maid: New Respond for Your Support Ticket (Support Ticket No: ' . $ticket->series_no . ')'; 
                $emailContent = (new NewSupportTicketEmail('Admin', $subject, $ticket))->render();
                $onesignal = new OneSignalService();
                $onesignal->sendEmail(
                    User::superAdminEmail(),
                    $subject,
                    $emailContent,
                );                
            }

            $data['ticket'] = $ticket;
            return response()->json([
                'status' => true,
                'message' => 'Ticket successfully added.',
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * Lets the ticket's own owner (or admin, via the Filament widget's
     * own separate path) post a new message on an existing ticket —
     * this is what makes it a genuine back-and-forth chat rather than
     * a one-shot complaint form. Previously this endpoint didn't exist
     * at all; index()/detail()/store()/orderLists() covered creating
     * and viewing tickets, but nothing let the customer actually reply
     * once a ticket was open.
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function reply(Request $request)
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
                'ticket_id' => 'required',
                'description' => 'required|string',
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validate->errors()
                ]);
            }

            $ticket = Ticket::find($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Record not found.',
                ]);
            }

            // Only the ticket's own owner may reply through this
            // endpoint — admin replies go through the separate
            // Filament widget, which isn't gated by ownership since
            // any staff member can respond to any ticket.
            if ($ticket->user_id != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to reply to this ticket.',
                ]);
            }

            $reply = new TicketReply();
            $reply->ticket_id = $ticket->id;
            $reply->description = $request->description;
            $reply->created_by = $user->id;
            $reply->save();

            // Reopen a resolved/closed ticket — a new message from the
            // customer means the issue isn't actually settled from
            // their side, regardless of what state admin last left it
            // in.
            if ($ticket->status !== Ticket::OPEN) {
                $ticket->status = Ticket::OPEN;
                $ticket->save();
            }

            $ticket->load(['order', 'user', 'replies']);
            $data['ticket'] = $ticket;
            return response()->json([
                'status' => true,
                'message' => 'Reply successfully added.',
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

    /**
     * [uploadFile description]
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public function uploadFile($file)
    {
        $ext = $file->extension();
        $path = '/automaid/images/tickets/' . uniqid().date('Ymdhis') . '.' . $ext;
        $manager = new ImageManager(new Driver());
        $img = $manager->read($file);
        // $img->resize(width: 200);
        $pointer = $img->encode()->toFilePointer();
        Storage::disk('s3')->put($path, $pointer, 'public');  
        return $path;
    }

    /**
     * [orderLists description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function orderLists(Request $request)
    {
        try {
            // check user
            $user = auth('sanctum')->user();            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ]);  
            }

            // check orders
            if (auth()->user()->roles->pluck('name')[0] == 'customer') {
                $orders = Order::where(['user_id' => auth()->user()->id])->latest()->limit(10)->get();
            }
            if (auth()->user()->roles->pluck('name')[0] == 'rider') {
                $orders = Order::has('rider')->latest()->limit(10)->get();
            }
            if (auth()->user()->roles->pluck('name')[0] == 'merchant') {
                $orders = Order::has('merchant')->latest()->limit(10)->get();
            }

            // return orders
            $data['orders'] = $orders;
            return response()->json([
                'status' => true,
                'message' => 'Order lists successfully retrieved.',
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ],500);
        }
    }

}



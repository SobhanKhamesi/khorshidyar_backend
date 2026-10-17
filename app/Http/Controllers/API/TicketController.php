<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\Request;

class TicketController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }

    public function indexUndo()
    {
        if (auth()->user()->role == User::ADMIN) {
            $data = Ticket::whereIn('status', [0])->orderby('created_at', 'DESC')->get();
        } else {
            $data = Ticket::where(function ($query) {
                $query->where('userId', auth()->id())
                    ->orWhere('user_id', auth()->id());
            })
                ->whereIn('status', [0])
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        return $this->apiController->respondData($data);
    }

    public function indexDone()
    {


        if (auth()->user()->role == User::ADMIN) {
            $data = Ticket::whereIn('status', [1])->orderby('created_at', 'DESC')->get();
        } else {
            $data = Ticket::where(function ($query) {
                $query->where('userId', auth()->id())
                    ->orWhere('user_id', auth()->id());
            })
                ->whereIn('status', [1])
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        return $this->apiController->respondData($data);
    }

    public function indexDoing()
    {

        if (auth()->user()->role == User::ADMIN) {
            $data = Ticket::whereIn('status',[2])->orderby('created_at', 'DESC')->get();
        } else {
            $data = Ticket::where(function ($query) {
                $query->where('userId', auth()->id())
                    ->orWhere('user_id', auth()->id());
            })
                ->whereIn('status',[2])
                ->orderBy('created_at', 'DESC')
                ->get();
        }
        return $this->apiController->respondData($data);
    }

    public function store(Request $request)
    {


        $this->validate($request, [
            'userId' => 'required',
            'customer' => 'required',
        ], [
            'userId.required' => 'مسئول انجام این تیکت را انتخاب نمایید',
            'customer.required' => 'فیلد فعالیت مربوط به را تکمیل نمایید'
        ]);
        $customer = Customer::findOrFail($request->input('customer'));
        $user = User::find($request->input('userId'));
        if (!$user) {
            return $this->apiController->respondNotFound();
        } else {
            $userId = $user->id;
        }


        $create = Ticket::create([
            'status' => Ticket::UNDO,
            'customer_id' => $customer->id,
            'userId' => $userId,
            'time' => $request->input('time'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'descriptionTicket' => $request->input('descriptionTicket'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Ticket) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function show(Ticket $ticket)
    {
        return $this->apiController->respondData($ticket);
    }

    public function update(Request $request)
    {
        $customer = Customer::findOrFail($request->input('customer'));
        $user = User::find($request->input('userId'));
        if (!$user) {
            return $this->apiController->respondNotFound();
        } else {
            $userId = $user->id;
        }

        $data = Ticket::findOrFail($request->input('id'));

        if ($data) {

            $update = $data->update([
                'userId' => $userId,
                'customer_id' => $customer->id,
                'time' => $request->input('time'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'descriptionTicket' => $request->input('descriptionTicket'),
                'user_id' => auth()->id()
            ]);

            if ($update) {
                return $this->apiController->respondUpdated();
            } else {
                return $this->apiController->respondInternalError();
            }

        } else {
            return $this->apiController->respondNotFound();
        }


    }

    public function destroy(Request $request)
    {

        $data = Ticket::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function comments(Request $request)
    {
        $letterId = $request->input('id');
        $comments = TicketComment::where('ticket_id', $letterId)->orderBy('created_at', 'DESC')->get();
        return $this->apiController->respondData($comments);
    }

    public function newComment(Request $request)
    {

        $this->validate($request, [
            'content' => 'required'
        ], [
            'content.required' => 'کامنتی بنویسید'
        ]);

        $ticket = Ticket::findOrFail($request->input('id'));

        $newComment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'content' => $request->input('content')
        ]);


        $status = $ticket->status;
        if ($status == Ticket::UNDO) {
            $ticket->update([
                'status' => Ticket::DOING
            ]);
        }


        return $this->apiController->respondCreated();
    }

    public function deleteComment(Request $request)
    {

        $data = TicketComment::findOrFail($request->input('id'));
        if ($data) {
            if ($data->delete()) {
                return $this->apiController->respondDeleted();
            } else {
                return $this->apiController->respondInternalError();
            }
        } else {
            return $this->apiController->respondNotFound();
        }

    }


    public function accept(Ticket $ticket)
    {
        $status = $ticket->status;

        if ($status == Ticket::UNDO || $status == Ticket::DOING) {
            $nextStatus = Ticket::DONE;
        } else {
            $nextStatus = $status;
        }


        $ticket->update([
            'status' => $nextStatus

        ]);

        return $this->apiController->respondUpdated();
    }

    public function attachments(Request $request)
    {

        $ticket = Ticket::findOrFail($request->input('id'));
        $data = Attachment::where('ticket_id', $ticket->id)->orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);

    }

    public function newAttachment(Request $request)
    {
        $ticket = Ticket::findOrFail($request->input('id'));

        $url = rand(100000, 9000000);
        $imageItem = $request->file('file');
        $imageType = $request->file('file')->extension();
        $newImageName = $url . '.' . $imageType;
        $imageItem->move(public_path('file'), $newImageName);

        $newComment = Attachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'file' => $newImageName,
            'description' => $this->apiHelper->getRealText($request, 'description')
        ]);

        $status = $ticket->status;
        if ($status == Ticket::UNDO) {
            $ticket->update([
                'status' => Ticket::DOING
            ]);
        }


        return $this->apiController->respondCreated();

    }

    public function deleteAttachment(Request $request)
    {

        $data = Attachment::find($request->input('id'));
        if ($data) {
            if ($data->delete()) {
                return $this->apiController->respondDeleted();
            } else {
                return $this->apiController->respondInternalError();
            }
        } else {
            return $this->apiController->respondNotFound();
        }

    }


}

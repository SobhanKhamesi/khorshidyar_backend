<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Models\Customer;
use App\Models\Referrer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReferrerController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }
    public function index()
    {
        $referrers = Referrer::all();
        return $this->apiController->respondData($referrers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $referrer = Referrer::create($request->only('name', 'description'));

        if ($referrer and $referrer instanceof Referrer) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function update(Referrer $referrer , Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $update = $referrer->update($request->only('name', 'description'));

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function destroy(Referrer $referrer)
    {
        Customer::where("referrer_id" , $referrer->id)->update(['referrer_id' => null]);
        if ($referrer->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function introduces(Referrer $referrer)
    {
        $data = Customer::where("referrer_id" , $referrer->id)->get();
        return $this->apiController->respondData($data);
    }
}

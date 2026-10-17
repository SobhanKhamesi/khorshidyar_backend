<?php

namespace App\Http\Controllers\API\Contracts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response as Res;

class ApiController extends Controller
{

    public function respond($data, $statusCode)
    {
        return \response($data, $statusCode);
    }

    public function respondMessage($message)
    {
        return $this->respond(
            [
                'message' => $message,
            ],
            Res::HTTP_OK);
    }

    public function respondData($data = null, $message = null)
    {
        return $this->respond(
            [
                'message' => $message,
                'data' => $data
            ],
            Res::HTTP_OK);
    }


    public function respondCreated($message = 'با موفقیت ایجاد شد', $data = null)
    {
        return $this->respond(
            [
                'message' => $message,
                'data' => $data
            ],
            Res::HTTP_CREATED);
    }

    public function respondUpdated($message = 'با موفقیت ویرایش شد', $data = null)
    {
        return $this->respond(
            [
                'message' => $message,
                'data' => $data
            ],
            Res::HTTP_OK);
    }

    public function respondDeleted($message = 'با موفقیت حذف شد', $data = null)
    {
        return $this->respond(
            [
                'message' => $message,
                'data' => $data
            ],
            Res::HTTP_OK);
    }

    public function respondInternalError($message = 'مشکلی در سمت سرور رخ داده است')
    {
        return $this->respond(
            [
                'message' => $message,
            ],
            Res::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function respondValidationError($message, $errors = [])
    {
        return $this->respond(
            [
                'message' => $message,
                'data' => $errors
            ],
            Res::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function respondUnauthorizedError($message = 'اعتبار سنجی حساب کاربری ناموفق بود')
    {
        return $this->respond(
            [
                'message' => $message,
            ],
            Res::HTTP_UNAUTHORIZED
        );
    }

    public function respondError($message)
    {
        return $this->respond(
            [
                'message' => $message,
            ],
            Res::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function respondNotFound($message = 'یافت نشد')
    {
        return $this->respond(
            [
                'message' => $message,
            ],
            Res::HTTP_NOT_FOUND);
    }

    public function respondForbidden($message = 'دسترسی ندارید')
    {
        return $this->respond(
            [
                'message' => $message,
            ],
            Res::HTTP_FORBIDDEN);
    }
}


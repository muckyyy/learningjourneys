
<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Chat API Routes
|--------------------------------------------------------------------------
|
| Routes for AI chat functionality in learning journeys
|
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/chat/start', [ChatController::class, 'startChat'])->name('api.chat.start');
    Route::post('/chat/submit', [ChatController::class, 'chatSubmit'])->name('api.chat.submit');
});

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Curzzo\CreateCurzzo;
use App\Actions\Curzzo\DeleteCurzzo;
use App\Actions\Curzzo\ReorderCurzzos;
use App\Actions\Curzzo\RequestCurzzoPreviewVideoUpload;
use App\Actions\Curzzo\ToggleCurzzoActive;
use App\Actions\Curzzo\UpdateCurzzo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCurzzoRequest;
use App\Http\Requests\ReorderCurzzosRequest;
use App\Http\Requests\UpdateCurzzoRequest;
use App\Http\Requests\UploadCurzzoPreviewVideoRequest;
use App\Http\Resources\CurzzoResource;
use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurzzoController extends Controller
{
    public function index(Community $community): AnonymousResourceCollection
    {
        $this->authorize('update', $community);

        return CurzzoResource::collection(
            $community->curzzos()->orderBy('position')->get()
        );
    }

    public function store(CreateCurzzoRequest $request, Community $community, CreateCurzzo $action): CurzzoResource
    {
        $curzzo = $action->execute($request->user(), $community, $request->validated());

        return new CurzzoResource($curzzo);
    }

    public function update(UpdateCurzzoRequest $request, Community $community, Curzzo $curzzo, UpdateCurzzo $action): CurzzoResource
    {
        abort_unless($curzzo->community_id === $community->id, 404);

        $action->execute($curzzo, $request->validated());

        return new CurzzoResource($curzzo);
    }

    public function destroy(Community $community, Curzzo $curzzo, DeleteCurzzo $action): JsonResponse
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $action->execute($curzzo);

        return response()->json(['ok' => true]);
    }

    public function reorder(ReorderCurzzosRequest $request, Community $community, ReorderCurzzos $action): JsonResponse
    {
        $action->execute($community, $request->validated('ids'));

        return response()->json(['ok' => true]);
    }

    public function toggleActive(Community $community, Curzzo $curzzo, ToggleCurzzoActive $action): CurzzoResource
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $action->execute($curzzo);

        return new CurzzoResource($curzzo);
    }

    public function uploadPreviewVideo(UploadCurzzoPreviewVideoRequest $request, Community $community, RequestCurzzoPreviewVideoUpload $action): JsonResponse
    {
        $result = $action->execute(
            $community,
            $request->validated('filename'),
            $request->validated('content_type'),
            $request->validated('size'),
        );

        return response()->json($result);
    }
}

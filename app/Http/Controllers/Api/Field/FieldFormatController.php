<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Field\StoreFieldFormatRequest;
use App\Http\Requests\Field\UpdateFieldFormatRequest;
use App\Models\FieldFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldFormatController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $formats = FieldFormat::query()
            ->withCount('fields')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($formats, 'Field formats retrieved successfully.');
    }

    public function store(StoreFieldFormatRequest $request): JsonResponse
    {
        $format = FieldFormat::create($request->validated());

        return $this->jsonResponse($format, 'Field format created successfully.', 201);
    }

    public function show(FieldFormat $fieldFormat): JsonResponse
    {
        return $this->jsonResponse($fieldFormat, 'Field format retrieved successfully.');
    }

    public function update(UpdateFieldFormatRequest $request, FieldFormat $fieldFormat): JsonResponse
    {
        $fieldFormat->update($request->validated());

        return $this->jsonResponse($fieldFormat->fresh(), 'Field format updated successfully.');
    }

    public function destroy(FieldFormat $fieldFormat): JsonResponse
    {
        $fieldFormat->delete();

        return $this->jsonResponse(null, 'Field format deleted successfully.');
    }
}

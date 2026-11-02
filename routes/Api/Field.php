<?php

use App\Http\Controllers\Api\Field\FieldController;
use App\Http\Controllers\Api\Field\FieldFormatController;
use App\Http\Controllers\Api\Field\FieldPriceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // ── Field Formats ──────────────────────────────────────────────────────
    Route::prefix('field-formats')->group(function () {
        Route::get('/',           [FieldFormatController::class, 'index'])->name('field-formats.index');
        Route::post('/',          [FieldFormatController::class, 'store'])->name('field-formats.store');
        Route::get('/{fieldFormat}',    [FieldFormatController::class, 'show'])->name('field-formats.show');
        Route::put('/{fieldFormat}',    [FieldFormatController::class, 'update'])->name('field-formats.update');
        Route::delete('/{fieldFormat}', [FieldFormatController::class, 'destroy'])->name('field-formats.destroy');
    });

    // ── Fields ─────────────────────────────────────────────────────────────
    Route::prefix('fields')->group(function () {
        Route::get('/',           [FieldController::class, 'index'])->name('fields.index');
        Route::get('/my',         [FieldController::class, 'my'])->name('fields.my');
        Route::post('/',          [FieldController::class, 'store'])->name('fields.store');
        Route::get('/{field}',    [FieldController::class, 'show'])->name('fields.show');

        // ── Field Reviews (delegated to the field's venue) ─────────────────
        Route::post('/{field}/reviews',         [FieldController::class, 'storeReview'])->name('fields.reviews.store');
        Route::get('/{field}/reviews/summary',  [FieldController::class, 'reviewsSummary'])->name('fields.reviews.summary');
        Route::put('/{field}',    [FieldController::class, 'update'])->name('fields.update');
        Route::delete('/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');

        // ── Field Pricing ──────────────────────────────────────────────────
        Route::prefix('{field}/prices')->group(function () {
            Route::get('/resolve', [FieldPriceController::class, 'resolve'])->name('field-prices.resolve');
            Route::get('/grid',    [FieldPriceController::class, 'grid'])->name('field-prices.grid');

            Route::get('/',           [FieldPriceController::class, 'index'])->name('field-prices.index');
            Route::post('/',          [FieldPriceController::class, 'store'])->name('field-prices.store');
            Route::get('/{price}',    [FieldPriceController::class, 'show'])->name('field-prices.show');
            Route::put('/{price}',    [FieldPriceController::class, 'update'])->name('field-prices.update');
            Route::delete('/{price}', [FieldPriceController::class, 'destroy'])->name('field-prices.destroy');

            Route::patch('/{price}/toggle', [FieldPriceController::class, 'toggle'])->name('field-prices.toggle');
        });
    });
});

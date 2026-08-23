# Design Document: Rental Shop Enhancements

## Architecture Overview

This enhancement adds four functional areas to the CK CRM rental shop: configurable product questions with answer collection, admin quick-action buttons on the bookings list, enhanced portal order detail pages, and UI timeline components. The architecture follows existing patterns — Blade views with Alpine.js interactivity, Tailwind CSS styling, controllers delegating to services, and DomPDF for PDF generation.

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Admin Panel (x-admin-layout, middleware: auth + is_admin)              │
├─────────────────────────────────────────────────────────────────────────┤
│  ShopProductController (edit/update) ─── Product Questions CRUD         │
│  BookingController (index) ─── Quick Actions (resend, advance, return)  │
│  ShopOrderController (show) ─── Answers Display + Fulfilment Timeline   │
│  BookingInspectionReportController (new) ─── PDF Download               │
├─────────────────────────────────────────────────────────────────────────┤
│  Portal (x-portal-layout, middleware: auth)                             │
├─────────────────────────────────────────────────────────────────────────┤
│  OrderController (show) ─── Rental Details, Inspections, Answers        │
│  CheckoutController ─── Question Rendering + Answer Collection          │
├─────────────────────────────────────────────────────────────────────────┤
│  Services                                                               │
├─────────────────────────────────────────────────────────────────────────┤
│  InspectionReportPdfService (new) ─── On-the-fly report generation      │
│  NotificationService (existing) ─── Resend confirmation                 │
│  FulfilmentStageService (existing) ─── Stage advancement                │
│  CheckoutService (existing, extended) ─── Answer persistence            │
│  BookingConfirmationPdfService (existing) ─── Confirmation PDF          │
├─────────────────────────────────────────────────────────────────────────┤
│  Models                                                                 │
├─────────────────────────────────────────────────────────────────────────┤
│  ProductQuestion (new) ─── product_id, label, input_type, options, etc  │
│  QuestionAnswer (new) ─── order_item_id, product_question_id, value     │
│  Product (extended) ─── hasMany ProductQuestion                          │
│  OrderItem (extended) ─── hasMany QuestionAnswer                         │
├─────────────────────────────────────────────────────────────────────────┤
│  Database (MySQL)                                                        │
├─────────────────────────────────────────────────────────────────────────┤
│  product_questions (new table)                                           │
│  question_answers (new table)                                            │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Component Design

### 1. Database Schema

#### New Table: `product_questions`

```php
Schema::create('product_questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->string('input_type'); // free_text, textarea, date, email, phone, select, number
    $table->json('options')->nullable(); // JSON array of choices for "select" type
    $table->boolean('is_required')->default(false);
    $table->unsignedInteger('display_order')->default(0);
    $table->timestamps();

    $table->index(['product_id', 'display_order']);
});
```

#### New Table: `question_answers`

```php
Schema::create('question_answers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_question_id')->constrained()->nullOnDelete();
    $table->text('answer_value')->nullable();
    $table->string('question_label'); // Snapshot of label at time of answer
    $table->string('question_type');  // Snapshot of input_type at time of answer
    $table->timestamps();

    $table->index('order_item_id');
    $table->index('product_question_id');
});
```

Design decisions:
- `question_label` and `question_type` are snapshot fields so that answer display remains correct even if the question is later edited or deleted (Req 1.7).
- `product_question_id` uses `nullOnDelete` rather than cascade — if a question is removed, the answer record persists with its snapshot fields.
- `options` is stored as JSON only for the "select" type; null otherwise.

---

### 2. New Models

#### ProductQuestion Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuestion extends Model
{
    protected $fillable = [
        'product_id',
        'label',
        'input_type',
        'options',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'display_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class);
    }
}
```

#### QuestionAnswer Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAnswer extends Model
{
    protected $fillable = [
        'order_item_id',
        'product_question_id',
        'answer_value',
        'question_label',
        'question_type',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productQuestion(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class);
    }
}
```

#### Model Relationship Extensions

**Product** — add:
```php
public function questions(): HasMany
{
    return $this->hasMany(ProductQuestion::class)->orderBy('display_order');
}
```

**OrderItem** — add:
```php
public function questionAnswers(): HasMany
{
    return $this->hasMany(QuestionAnswer::class);
}
```

---

### 3. Admin — Product Question Management (Requirements 1)

#### Controller: ShopProductController

Extend `edit()` to eager-load questions:
```php
public function edit(Product $product): View
{
    $product->load('questions');
    // ...existing code...
    return view('admin.shop.products.edit', compact('product', ...));
}
```

Extend `update()` to handle question sync:
```php
// Within the update method, after product save:
if ($request->has('questions')) {
    $this->syncProductQuestions($product, $request->input('questions', []));
}
```

New private method:
```php
private function syncProductQuestions(Product $product, array $questionsData): void
{
    $existingIds = $product->questions()->pluck('id')->toArray();
    $incomingIds = collect($questionsData)->pluck('id')->filter()->toArray();

    // Delete removed questions (soft — answers preserved via nullOnDelete FK)
    $product->questions()->whereNotIn('id', $incomingIds)->delete();

    // Upsert questions
    foreach ($questionsData as $index => $questionData) {
        $product->questions()->updateOrCreate(
            ['id' => $questionData['id'] ?? null],
            [
                'label' => $questionData['label'],
                'input_type' => $questionData['input_type'],
                'options' => $questionData['input_type'] === 'select'
                    ? ($questionData['options'] ?? [])
                    : null,
                'is_required' => (bool) ($questionData['is_required'] ?? false),
                'display_order' => $index,
            ]
        );
    }
}
```

#### View: `admin/shop/products/edit.blade.php`

Add a "Custom Questions" section using Alpine.js for dynamic question management:

```html
<!-- Product Questions Section -->
<div x-data="productQuestions(@js($product->questions))" class="mt-8">
    <h3 class="text-lg font-semibold mb-4">Custom Questions</h3>
    
    <template x-for="(question, index) in questions" :key="question._key">
        <div class="border rounded-lg p-4 mb-3 bg-gray-50">
            <!-- Label input -->
            <!-- Input type select -->
            <!-- Options editor (shown when type === 'select') -->
            <!-- Required toggle -->
            <!-- Remove button -->
            <!-- Hidden inputs for form submission -->
        </div>
    </template>
    
    <button type="button" @click="addQuestion()" class="btn-secondary">
        + Add Question
    </button>
</div>
```

Alpine component supports drag-and-drop reordering via positional controls (move up/down buttons) for simplicity, avoiding external library dependencies.

---

### 4. Portal — Question Answer Collection at Checkout (Requirement 2)

#### Controller: CheckoutController

During checkout form display, load product questions:
```php
// When rendering the checkout view, for each cart item:
$cartItems = collect($cart)->map(function ($item) {
    $product = Product::with('questions')->find($item['product_id']);
    $item['questions'] = $product->questions;
    return $item;
});
```

#### Validation in CheckoutService

Extend `processCheckout()` or `processCheckoutFromArray()` to validate and store answers:

```php
// Validate question answers for each cart item
foreach ($cartItems as $index => $item) {
    $product = Product::with('questions')->find($item['product_id']);
    foreach ($product->questions as $question) {
        $answerKey = "answers.{$item['product_id']}.{$question->id}";
        if ($question->is_required) {
            $rules[$answerKey] = 'required|string|min:1';
        } else {
            $rules[$answerKey] = 'nullable|string';
        }
    }
}
```

#### Answer Persistence

After order item creation, store answers:
```php
private function storeQuestionAnswers(OrderItem $orderItem, array $answers, Product $product): void
{
    foreach ($product->questions as $question) {
        $value = $answers[$question->id] ?? null;
        
        // Skip if optional and empty
        if (!$question->is_required && empty($value)) {
            continue;
        }

        QuestionAnswer::create([
            'order_item_id' => $orderItem->id,
            'product_question_id' => $question->id,
            'answer_value' => $value,
            'question_label' => $question->label,
            'question_type' => $question->input_type,
        ]);
    }
}
```

#### View: Checkout Form

Render questions per product in the checkout form:
```html
@foreach($item['questions'] as $question)
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">
            {{ $question->label }}
            @if($question->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>

        @switch($question->input_type)
            @case('free_text')
                <input type="text" name="answers[{{ $item['product_id'] }}][{{ $question->id }}]" ... />
                @break
            @case('textarea')
                <textarea name="answers[{{ $item['product_id'] }}][{{ $question->id }}]" ...></textarea>
                @break
            @case('date')
                <input type="date" ... />
                @break
            @case('email')
                <input type="email" ... />
                @break
            @case('phone')
                <input type="tel" ... />
                @break
            @case('number')
                <input type="number" ... />
                @break
            @case('select')
                <select ...>
                    <option value="">-- Select --</option>
                    @foreach($question->options ?? [] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @break
        @endswitch
    </div>
@endforeach
```

---

### 5. Admin — Question Answer Display on Order Detail (Requirement 3)

#### Controller: ShopOrderController::show()

Extend eager loading:
```php
public function show(Order $order): View
{
    $order->load([
        'items.booking.inspections',
        'items.product',
        'items.questionAnswers', // Add this
        // ...existing loads
    ]);

    return view('admin.shop.orders.show', compact('order'));
}
```

#### View: `admin/shop/orders/show.blade.php`

Within each order item block:
```html
@if($item->questionAnswers->isNotEmpty())
    <div class="mt-4 border-t pt-3">
        <h5 class="text-sm font-medium text-gray-600 mb-2">Customer Responses</h5>
        <dl class="grid grid-cols-1 gap-2">
            @foreach($item->questionAnswers as $answer)
                <div>
                    <dt class="text-xs text-gray-500">{{ $answer->question_label }}</dt>
                    <dd class="text-sm text-gray-900">{{ $answer->answer_value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
```

---

### 6. Admin Quick Actions on Bookings List (Requirements 4–7)

#### Controller: BookingController

Add three new action methods:

```php
/**
 * Resend booking confirmation email (Req 4).
 */
public function resendConfirmation(Booking $booking): RedirectResponse
{
    try {
        $this->notificationService->notifyCustomerBookingConfirmed($booking);
        return redirect()->back()->with('success', 'Confirmation email resent successfully.');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Failed to resend confirmation: ' . $e->getMessage());
    }
}

/**
 * Advance booking to the next fulfilment stage (Req 6).
 */
public function advanceStage(Booking $booking): RedirectResponse
{
    $nextStage = $this->fulfilmentStageService->getNextStage($booking);

    if (!$nextStage) {
        return redirect()->back()->with('error', 'Booking is already at the final stage.');
    }

    try {
        $this->fulfilmentStageService->advance($booking, $nextStage);
        return redirect()->back()->with('success', 'Booking advanced to "' . $nextStage . '".');
    } catch (InvalidArgumentException $e) {
        return redirect()->back()->with('error', $e->getMessage());
    }
}

/**
 * Mark booking as returned — shortcut for advance to "returned" (Req 7).
 */
public function markReturnedFromList(Booking $booking): RedirectResponse
{
    if ($booking->fulfilment_stage !== 'checked_out') {
        return redirect()->back()->with('error', 'Only checked-out bookings can be marked as returned.');
    }

    try {
        $this->fulfilmentStageService->advance($booking, 'returned');
        $booking->update(['returned_at' => now()]);
        return redirect()->back()->with('success', 'Booking marked as returned.');
    } catch (InvalidArgumentException $e) {
        return redirect()->back()->with('error', $e->getMessage());
    }
}
```

#### View: `admin/shop/bookings/index.blade.php`

Add action buttons in each booking row, conditionally displayed:

```html
<td class="px-4 py-3 flex items-center gap-2">
    {{-- Resend Confirmation (always visible) --}}
    <form method="POST" action="{{ route('admin.bookings.resend-confirmation', $booking) }}">
        @csrf
        <button type="submit" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200">
            Resend Confirmation
        </button>
    </form>

    {{-- Advance Stage (hidden at final stage) --}}
    @if($booking->fulfilment_stage !== 'inspected')
        @php $nextStage = app(FulfilmentStageService::class)->getNextStage($booking); @endphp
        <form method="POST" action="{{ route('admin.bookings.advance-stage', $booking) }}">
            @csrf
            <button type="submit" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">
                Advance → {{ ucfirst(str_replace('_', ' ', $nextStage)) }}
            </button>
        </form>
    @endif

    {{-- Mark Returned (only when checked_out) --}}
    @if($booking->fulfilment_stage === 'checked_out')
        <form method="POST" action="{{ route('admin.bookings.mark-returned-list', $booking) }}">
            @csrf
            <button type="submit" class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded hover:bg-yellow-200">
                Mark Returned
            </button>
        </form>
    @endif

    {{-- Download Inspection Report (only when inspections exist) --}}
    @if($booking->inspections_count > 0)
        <a href="{{ route('admin.bookings.inspection-report', $booking) }}"
           class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded hover:bg-purple-200">
            Download Inspection Report
        </a>
    @endif
</td>
```

The bookings list query in `BookingController::index()` will be extended to eager-load `withCount('inspections')` for conditional button display.

---

### 7. Inspection Report PDF Service (Requirement 5)

#### New Service: InspectionReportPdfService

```php
<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class InspectionReportPdfService
{
    /**
     * Generate an inspection report PDF for a booking.
     * Returns the PDF instance for streaming (not stored).
     */
    public function generate(Booking $booking): \Barryvdh\DomPDF\PDF
    {
        $booking->loadMissing(['product', 'customer', 'inspections.inspector']);

        $data = [
            'booking' => $booking,
            'customer' => $booking->customer,
            'product' => $booking->product,
            'inspections' => $booking->inspections->sortBy('inspected_at'),
            'companyName' => Setting::get('company_name', config('app.name', 'Company')),
        ];

        $pdf = Pdf::loadView('pdf.inspection-report', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }
}
```

#### New Controller: BookingInspectionReportController

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\InspectionReportPdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingInspectionReportController extends Controller
{
    public function __construct(
        protected InspectionReportPdfService $pdfService
    ) {}

    public function download(Booking $booking): StreamedResponse
    {
        abort_unless($booking->inspections()->exists(), 404);

        $pdf = $this->pdfService->generate($booking);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'inspection-report-booking-' . $booking->id . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
```

#### PDF View: `resources/views/pdf/inspection-report.blade.php`

Structured layout containing:
- Header: company name, "Inspection Report" title
- Booking reference (BKG-{id}), product name, customer name
- For each inspection: type badge, photos rendered inline as base64 images, condition notes, inspector name, inspection timestamp

Photos are embedded as base64 data URIs, read from storage at generation time.

---

### 8. Portal — Enhanced Order Detail (Requirements 8–11, 13–14)

#### Controller: Portal\OrderController::show()

Extend eager loading:
```php
public function show(Request $request, Order $order): View
{
    abort_unless($order->company_id === $request->user()->company_id, 404);

    $order->load([
        'items.booking.checkoutInspection',
        'items.booking.returnInspection',
        'items.product',
        'items.questionAnswers',
    ]);

    return view('portal.orders.show', compact('order'));
}
```

#### Fulfilment Stage Label Mapping

Defined as a constant or helper, used in both admin and portal views:

```php
// App\Support\FulfilmentStageLabels or as a Blade component prop
const CUSTOMER_STAGE_LABELS = [
    'ordered' => 'Order Placed',
    'packing' => 'Being Prepared',
    'ready' => 'Ready for Collection',
    'checked_out' => 'With You',
    'returned' => 'Returned',
    'inspected' => 'Complete',
];

const ADMIN_STAGE_LABELS = [
    'ordered' => 'Ordered',
    'packing' => 'Packing',
    'ready' => 'Ready',
    'checked_out' => 'Checked Out',
    'returned' => 'Returned',
    'inspected' => 'Inspected',
];
```

#### View Architecture: `portal/orders/show.blade.php`

The order detail page iterates over order items. For rental items (`product_type === 'equipment_rental'`):

```html
@foreach($order->items as $item)
    <div class="border rounded-lg p-4 md:p-6 mb-4">
        {{-- Product name, price, quantity --}}
        <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-3">
            <h3 class="font-semibold text-lg">{{ $item->product_name }}</h3>
            <span class="text-gray-600">£{{ number_format($item->price, 2) }}</span>
        </div>

        {{-- Rental booking details --}}
        @if($item->product_type === 'equipment_rental' && $item->booking)
            @include('portal.orders.partials.booking-details', ['booking' => $item->booking])
        @endif

        {{-- Question answers --}}
        @if($item->questionAnswers->isNotEmpty())
            @include('portal.orders.partials.question-answers', ['answers' => $item->questionAnswers])
        @endif
    </div>
@endforeach
```

#### Partial: `portal/orders/partials/booking-details.blade.php`

```html
<div class="mt-4 space-y-4">
    {{-- Dates --}}
    <div class="flex flex-col sm:flex-row gap-4">
        <div>
            <span class="text-sm text-gray-500">Start Date</span>
            <p class="font-medium">{{ $booking->start_date->format('d M Y') }}</p>
        </div>
        <div>
            <span class="text-sm text-gray-500">End Date</span>
            <p class="font-medium">{{ $booking->end_date->format('d M Y') }}</p>
        </div>
        <div>
            <span class="text-sm text-gray-500">Status</span>
            <p class="font-medium">{{ $customerStageLabels[$booking->fulfilment_stage] ?? $booking->fulfilment_stage }}</p>
        </div>
    </div>

    {{-- Booking Timeline --}}
    @include('portal.orders.partials.booking-timeline', ['booking' => $booking])

    {{-- Download Confirmation --}}
    <a href="{{ route('portal.orders.booking-confirmation', $booking) }}"
       class="inline-flex items-center min-w-[44px] min-h-[44px] px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
        Download Confirmation
    </a>

    {{-- Inspection Reports --}}
    @if($booking->checkoutInspection || $booking->returnInspection)
        @include('portal.orders.partials.inspections', ['booking' => $booking])
    @endif
</div>
```

---

### 9. Timeline Components (Requirements 12–13)

#### Blade Component: `x-fulfilment-timeline`

A reusable component accepting `$currentStage`, `$labels` (admin or customer), and `$layout` ('horizontal'|'responsive'):

```php
// app/View/Components/FulfilmentTimeline.php
<?php

namespace App\View\Components;

use Illuminate\View\Component;

class FulfilmentTimeline extends Component
{
    public array $stages;
    public string $currentStage;
    public array $labels;
    public string $layout;

    public function __construct(string $currentStage, array $labels, string $layout = 'responsive')
    {
        $this->currentStage = $currentStage;
        $this->labels = $labels;
        $this->layout = $layout;
        $this->stages = ['ordered', 'packing', 'ready', 'checked_out', 'returned', 'inspected'];
    }

    public function stageStatus(string $stage): string
    {
        $currentIndex = array_search($this->currentStage, $this->stages);
        $stageIndex = array_search($stage, $this->stages);

        if ($stageIndex < $currentIndex) return 'completed';
        if ($stageIndex === $currentIndex) return 'active';
        return 'future';
    }

    public function render()
    {
        return view('components.fulfilment-timeline');
    }
}
```

#### Component View: `resources/views/components/fulfilment-timeline.blade.php`

```html
<div class="{{ $layout === 'responsive' ? 'flex flex-col md:flex-row' : 'flex flex-row' }} items-start md:items-center gap-1 md:gap-2">
    @foreach($stages as $stage)
        @php $status = $stageStatus($stage); @endphp
        <div class="flex items-center gap-1 md:gap-2">
            {{-- Stage indicator --}}
            <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium
                {{ $status === 'completed' ? 'bg-green-500 text-white' : '' }}
                {{ $status === 'active' ? 'bg-blue-500 text-white ring-2 ring-blue-200' : '' }}
                {{ $status === 'future' ? 'bg-gray-200 text-gray-500' : '' }}">
                @if($status === 'completed')
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">...</svg>
                @else
                    {{ $loop->iteration }}
                @endif
            </div>
            <span class="text-xs {{ $status === 'active' ? 'font-semibold text-blue-700' : 'text-gray-600' }}">
                {{ $labels[$stage] ?? $stage }}
            </span>
            {{-- Connector line (except last) --}}
            @unless($loop->last)
                <div class="hidden md:block w-6 h-0.5 {{ $status === 'completed' ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                <div class="md:hidden w-0.5 h-4 mx-auto {{ $status === 'completed' ? 'bg-green-500' : 'bg-gray-200' }}"></div>
            @endunless
        </div>
    @endforeach
</div>
```

Usage in admin order detail:
```html
<x-fulfilment-timeline :current-stage="$booking->fulfilment_stage" :labels="ADMIN_STAGE_LABELS" layout="horizontal" />
```

Usage in portal order detail (responsive — horizontal on desktop, vertical on mobile):
```html
<x-fulfilment-timeline :current-stage="$booking->fulfilment_stage" :labels="CUSTOMER_STAGE_LABELS" layout="responsive" />
```

---

### 10. Portal — Inspection Display (Requirement 10)

#### Partial: `portal/orders/partials/inspections.blade.php`

```html
<div class="mt-6 space-y-4">
    <h4 class="font-medium text-gray-800">Equipment Inspections</h4>

    @if($booking->checkoutInspection)
        @include('portal.orders.partials.inspection-card', [
            'inspection' => $booking->checkoutInspection,
            'title' => 'Checkout Inspection'
        ])
    @endif

    @if($booking->returnInspection)
        @include('portal.orders.partials.inspection-card', [
            'inspection' => $booking->returnInspection,
            'title' => 'Return Inspection'
        ])
    @endif
</div>
```

#### Partial: `portal/orders/partials/inspection-card.blade.php`

```html
<div class="border rounded-lg p-4">
    <div class="flex justify-between items-center mb-3">
        <h5 class="font-medium text-sm">{{ $title }}</h5>
        <span class="text-xs text-gray-500">{{ $inspection->inspected_at->format('d M Y, H:i') }}</span>
    </div>

    {{-- Photo gallery --}}
    @if(!empty($inspection->photos))
        <div class="flex gap-2 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($inspection->photos as $photo)
                <button @click="openLightbox('{{ $photo }}')"
                        class="flex-shrink-0 w-20 h-20 sm:w-full sm:h-auto sm:aspect-square rounded-lg overflow-hidden">
                    <img src="{{ route('admin.bookings.inspection-photo', $photo) }}"
                         class="w-full h-full object-cover" alt="Inspection photo" loading="lazy" />
                </button>
            @endforeach
        </div>
    @endif

    {{-- Condition notes (NO inspector name, NO damage flag) --}}
    @if($inspection->condition_notes)
        <p class="mt-3 text-sm text-gray-700">{{ $inspection->condition_notes }}</p>
    @endif
</div>
```

Key points:
- Inspector identity and damage_flagged are NOT passed to the portal view (Req 10.5).
- Photos render as a scrollable horizontal row on mobile, multi-column grid on larger screens (Req 14.3).
- Thumbnails are clickable with Alpine.js lightbox for full-size viewing (Req 10.3).
- Touch targets exceed 44x44px (w-20 h-20 = 80px on mobile) (Req 14.5).

---

### 11. Route Additions

```php
// routes/web.php — Admin group (middleware: auth, is_admin)
Route::prefix('admin')->middleware(['auth', 'is_admin'])->group(function () {
    // Existing booking routes...
    Route::post('/bookings/{booking}/resend-confirmation', [BookingController::class, 'resendConfirmation'])
        ->name('admin.bookings.resend-confirmation');
    Route::post('/bookings/{booking}/advance-stage', [BookingController::class, 'advanceStage'])
        ->name('admin.bookings.advance-stage');
    Route::post('/bookings/{booking}/mark-returned-list', [BookingController::class, 'markReturnedFromList'])
        ->name('admin.bookings.mark-returned-list');
    Route::get('/bookings/{booking}/inspection-report', [BookingInspectionReportController::class, 'download'])
        ->name('admin.bookings.inspection-report');
});

// Portal group (middleware: auth) — existing routes, no new routes needed
// The portal already has:
//   - portal.orders.show (extended view)
//   - portal.orders.booking-confirmation (existing download route)
```

The portal inspection photo route will need to be accessible from the portal. Either expose a portal-specific route for serving photos, or share the existing `admin.bookings.inspection-photo` route with portal middleware:

```php
// Portal photo access route
Route::get('/portal/inspection-photo/{path}', [Portal\InspectionPhotoController::class, 'show'])
    ->where('path', '.*')
    ->middleware('auth')
    ->name('portal.inspection-photo');
```

---

### 12. Mobile Responsiveness (Requirement 14)

The portal order detail views use Tailwind responsive utilities throughout:

- `flex flex-col sm:flex-row` — stacks on mobile, horizontal on tablet+
- `grid grid-cols-1 md:grid-cols-2` — single column on mobile, two columns on desktop
- `overflow-x-auto` — horizontal scrolling for photo galleries on small screens
- `min-w-[44px] min-h-[44px]` — ensures touch target compliance on all buttons
- Timeline component uses `flex-col md:flex-row` for responsive horizontal/vertical switching
- All text uses Tailwind's default responsive font scaling

No custom breakpoint CSS is needed; Tailwind's `sm:` (640px), `md:` (768px), and `lg:` (1024px) prefixes handle the layout switching cleanly.

---

## Error Handling

| Scenario | Handling |
|----------|----------|
| Resend confirmation fails (email error) | Catch exception, flash error with reason, redirect back |
| Advance stage fails (unmet pre-conditions) | FulfilmentStageService throws InvalidArgumentException, flash error message |
| Mark returned on non-checked_out booking | Guard clause returns early with error flash |
| Inspection report for booking with no inspections | abort(404) in controller |
| Product question validation fails (missing label/type) | Laravel validation errors, form re-displays |
| Checkout with missing required answers | Validation errors, checkout form re-displays |
| Inspection photo file missing from storage | Return 404 response |
| PDF generation fails (DomPDF error) | Catch exception, log, redirect with error flash |
| Booking confirmation PDF not found and generation fails | Redirect with error (existing behaviour preserved) |

---

## File Structure

```
app/
├── Http/Controllers/
│   ├── Admin/
│   │   ├── BookingController.php            (extended: resendConfirmation, advanceStage, markReturnedFromList)
│   │   ├── BookingInspectionReportController.php  (new)
│   │   ├── ShopProductController.php        (extended: question sync in update)
│   │   └── ShopOrderController.php          (extended: eager load answers)
│   └── Portal/
│       ├── OrderController.php              (extended: eager load answers, inspections)
│       └── InspectionPhotoController.php    (new: serve photos to portal users)
├── Models/
│   ├── ProductQuestion.php                  (new)
│   ├── QuestionAnswer.php                   (new)
│   ├── Product.php                          (extended: questions() relationship)
│   └── OrderItem.php                        (extended: questionAnswers() relationship)
├── Services/
│   └── InspectionReportPdfService.php       (new)
├── View/Components/
│   └── FulfilmentTimeline.php               (new)
database/migrations/
├── xxxx_create_product_questions_table.php   (new)
├── xxxx_create_question_answers_table.php    (new)
resources/views/
├── admin/shop/
│   ├── products/edit.blade.php              (extended: questions section)
│   ├── bookings/index.blade.php             (extended: quick action buttons)
│   └── orders/show.blade.php                (extended: answers, timeline)
├── portal/orders/
│   ├── show.blade.php                       (extended: rental details, timeline, inspections, answers)
│   └── partials/
│       ├── booking-details.blade.php        (new)
│       ├── booking-timeline.blade.php       (new — wraps x-fulfilment-timeline)
│       ├── inspections.blade.php            (new)
│       ├── inspection-card.blade.php        (new)
│       └── question-answers.blade.php       (new)
├── components/
│   └── fulfilment-timeline.blade.php        (new)
├── pdf/
│   └── inspection-report.blade.php          (new)
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Product questions display order invariant

*For any* Product with N ProductQuestions having varying display_order values, retrieving questions (via the `questions()` relationship or at checkout) must always return them sorted by display_order ascending.

**Validates: Requirements 1.6, 2.1**

### Property 2: Question answer preservation on question modification

*For any* ProductQuestion that has one or more associated QuestionAnswer records, deleting or editing that ProductQuestion must leave all existing QuestionAnswer records intact with their snapshot fields unchanged.

**Validates: Requirements 1.7**

### Property 3: Checkout validation respects required flag

*For any* ProductQuestion, checkout form submission must reject if the question's `is_required` is true and the provided answer is empty/missing, and must accept if `is_required` is false regardless of answer presence.

**Validates: Requirements 2.3, 2.4**

### Property 4: Question answer round-trip persistence

*For any* set of ProductQuestions on a Product and corresponding non-empty answers submitted at checkout, after successful checkout completion, a QuestionAnswer record must exist for each answered question linked to the correct OrderItem and ProductQuestion, with the submitted value stored in `answer_value`.

**Validates: Requirements 2.5**

### Property 5: Input type to HTML control mapping

*For any* ProductQuestion with a given `input_type`, the rendered checkout form must contain the corresponding HTML input element: free_text → `<input type="text">`, textarea → `<textarea>`, date → `<input type="date">`, email → `<input type="email">`, phone → `<input type="tel">`, number → `<input type="number">`, select → `<select>`.

**Validates: Requirements 2.2**

### Property 6: Question answers grouped under correct order item

*For any* Order containing multiple OrderItems each with QuestionAnswer records, both the admin order detail and portal order detail must display each answer grouped under its owning OrderItem (matched by `order_item_id`), showing the `question_label` and `answer_value`.

**Validates: Requirements 3.1, 3.2, 11.1, 11.2**

### Property 7: Inspection report button visibility

*For any* Booking in the admin bookings list, the "Download Inspection Report" button is rendered if and only if that Booking has at least one associated BookingInspection record.

**Validates: Requirements 5.1, 5.5**

### Property 8: Inspection report PDF content completeness

*For any* Booking with one or more BookingInspection records, the generated Inspection_Report_PDF must contain: the booking reference (BKG-{id}), product name, customer name, and for each inspection: the type (checkout/return), condition notes, inspector name, and inspection timestamp.

**Validates: Requirements 5.3**

### Property 9: Advance Stage button visibility and label correctness

*For any* Booking in the admin bookings list, the "Advance Stage" button is visible if and only if the booking's fulfilment_stage is not "inspected", and when visible, its label displays the next sequential stage from the defined stage order.

**Validates: Requirements 6.1, 6.5**

### Property 10: Mark Returned button conditional visibility

*For any* Booking in the admin bookings list, the "Mark Returned" button is visible if and only if the booking's fulfilment_stage equals "checked_out".

**Validates: Requirements 7.1, 7.5**

### Property 11: Portal rental booking detail display

*For any* OrderItem with `product_type` of "equipment_rental" and an associated Booking, the portal order detail page must display the booking's start_date, end_date, and the customer-facing stage label corresponding to the booking's current fulfilment_stage.

**Validates: Requirements 8.1, 8.2, 8.3**

### Property 12: Portal inspection privacy filter

*For any* BookingInspection rendered on the portal order detail page, the output must NOT contain the `inspected_by` user name or the `damage_flagged` boolean value. Only `condition_notes`, `photos`, and `inspected_at` are displayed.

**Validates: Requirements 10.5**

### Property 13: Portal inspection display completeness

*For any* Booking with a checkout or return BookingInspection, the portal order detail page must display that inspection's photos, condition notes, and inspected_at timestamp.

**Validates: Requirements 10.1, 10.2, 10.4**

### Property 14: Fulfilment timeline stage styling correctness

*For any* Booking at fulfilment stage index N (0-based in the sequence [ordered, packing, ready, checked_out, returned, inspected]), the timeline component must render stages at indices 0..N-1 with "completed" styling, the stage at index N with "active" styling, and stages at indices N+1..5 with "future" styling.

**Validates: Requirements 12.2, 13.2**

### Property 15: Customer-facing stage label mapping

*For any* valid fulfilment_stage value, the portal timeline and status display must render the corresponding customer label: ordered → "Order Placed", packing → "Being Prepared", ready → "Ready for Collection", checked_out → "With You", returned → "Returned", inspected → "Complete".

**Validates: Requirements 8.3, 13.3**

---

## Component Design (Requirements 15–20)

### 13. Booking Lifecycle State Machine (Requirement 15)

The booking lifecycle is governed by two parallel state tracks that evolve independently but with coordination:

#### State Machine Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│ Booking_Status (high-level lifecycle)                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────┐    start_date + paid    ┌────────┐                   │
│  │ confirmed │ ──────────────────────► │ active │                   │
│  └───────────┘                         └────────┘                   │
│       │                                     │                       │
│       │ cancel/expire                       │ mark returned         │
│       ▼                                     ▼                       │
│  ┌───────────┐                         ┌──────────┐                 │
│  │ cancelled │ ◄─────────────────────── │ returned │                │
│  └───────────┘    cancel                └──────────┘                │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ Fulfilment_Stage (operational workflow — strictly sequential)        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ordered ──► packing ──► ready ──► checked_out ──► returned ──► inspected │
│                                                                     │
│  Trigger:     Trigger:    Trigger:    Trigger:       Trigger:       │
│  assets       all assets  checkout    admin marks    return         │
│  assigned     assigned    inspection  returned       inspection     │
│  OR payment   AND paid    completed                  completed      │
│  confirmed                                                          │
└─────────────────────────────────────────────────────────────────────┘
```

#### Transition Table

| From Stage | To Stage | Trigger | Pre-Conditions |
|---|---|---|---|
| ordered | packing | Assets assigned OR payment confirmed | Order must be paid |
| packing | ready | Admin advances | At least 1 asset assigned |
| ready | checked_out | Checkout inspection submitted | Checkout inspection exists |
| checked_out | returned | Admin marks returned | None |
| returned | inspected | Return inspection submitted | Return inspection exists |

| From Status | To Status | Trigger | Notes |
|---|---|---|---|
| confirmed | active | Scheduler (start_date arrives) | Order must be paid |
| confirmed | cancelled | Admin cancels OR payment expires | — |
| active | returned | Admin marks returned | Syncs with fulfilment_stage |
| active | cancelled | Admin cancels | — |

#### Validation Rules

The `FulfilmentStageService` enforces:
1. **Sequential-only transitions** — no stage skipping. Target must be `STAGES[currentIndex + 1]`.
2. **No backward transitions** — once advanced, stages cannot revert.
3. **Pre-condition gates** — each transition has specific requirements that must be met before proceeding.

#### Status-Stage Synchronization

When `fulfilment_stage` transitions to `returned`, the `Booking_Status` should also transition to `returned` if currently `active`. This synchronization is handled in the `markReturned` logic:

```php
// In the markReturned action (both web admin and API):
$booking->update(['status' => 'returned', 'returned_at' => now()]);
$this->fulfilmentStageService->advance($booking, 'returned');
```

No changes to `FulfilmentStageService::advance()` are needed for this — it already handles the fulfilment_stage side. The status update is the responsibility of the calling controller/action.

---

### 14. Backend Extension — Signature Storage on Inspect Endpoint (Requirement 19.5)

#### API Change: Extend `inspect` Endpoint

The existing endpoint at `POST /api/admin/shop/orders/{order}/bookings/{booking}/inspect` is extended to accept an additional optional field:

```php
// Updated validation rules in Api\Admin\ShopOrderController::inspect()
$validated = $request->validate([
    'photos' => 'required|array|min:1|max:10',
    'photos.*' => 'required|image|mimes:jpeg,png,jpg|max:10240',
    'condition_notes' => 'nullable|string|max:2000',
    'damage_flagged' => 'nullable|boolean',
    'signature_data' => 'nullable|string|max:500000', // base64 PNG, ~375KB max
]);
```

#### Storage Logic

After creating the checkout inspection, store the signature on the Booking model:

```php
// Within the checkout inspection branch of inspect():
$this->bookingInspectionService->createCheckoutInspection($booking, $photos, $notes, $adminId);

// Store signature if provided
if (!empty($validated['signature_data'])) {
    $booking->update(['signature_data' => $validated['signature_data']]);
}

if ($booking->fulfilment_stage === 'ready') {
    $this->fulfilmentStageService->advance($booking, 'checked_out');
}
```

The `signature_data` field already exists on the `bookings` table as a nullable text column. No migration is needed.

#### Admin Panel Display

In `admin/shop/orders/show.blade.php`, within the checkout inspection section:

```html
@if($booking->signature_data)
    <div class="mt-4">
        <h5 class="text-sm font-medium text-gray-600 mb-2">Customer Signature</h5>
        <img src="data:image/png;base64,{{ $booking->signature_data }}"
             alt="Customer signature"
             class="border rounded p-2 bg-white max-w-xs h-auto" />
    </div>
@endif
```

---

### 15. iOS CheckoutInspectionView — Handover Mode (Requirement 16)

#### Architecture

The checkout flow uses a multi-step sheet presented from `BookingDetailView`. It follows the existing `@Observable` ViewModel pattern.

```
BookingDetailView
  └── .sheet(isPresented: $showCheckoutSheet)
        └── CheckoutInspectionView
              ├── Step 1: PhotoCaptureStep (camera/library)
              ├── Step 2: ConditionNotesStep (optional text)
              ├── Step 3: SignatureStep (optional canvas)
              └── Step 4: ReviewAndSubmit
```

#### ViewModel: CheckoutInspectionViewModel

```swift
import SwiftUI
import PhotosUI

@Observable
final class CheckoutInspectionViewModel {
    // State
    var currentStep: CheckoutStep = .photos
    var capturedPhotos: [UIImage] = []
    var conditionNotes: String = ""
    var signatureImage: UIImage? = nil
    var agreementText: String? = nil
    
    var isSubmitting = false
    var errorMessage: String? = nil
    var isComplete = false
    
    // Dependencies
    private let apiClient: APIClient
    private let bookingId: Int
    private let orderId: Int
    
    enum CheckoutStep: Int, CaseIterable {
        case photos = 0
        case notes = 1
        case signature = 2
        case review = 3
        
        var title: String {
            switch self {
            case .photos: return "Photos"
            case .notes: return "Condition Notes"
            case .signature: return "Signature"
            case .review: return "Review & Submit"
            }
        }
    }
    
    init(apiClient: APIClient, bookingId: Int, orderId: Int, agreementText: String? = nil) {
        self.apiClient = apiClient
        self.bookingId = bookingId
        self.orderId = orderId
        self.agreementText = agreementText
    }
    
    var canSubmit: Bool {
        !capturedPhotos.isEmpty && !isSubmitting
    }
    
    func nextStep() {
        guard let nextIndex = CheckoutStep(rawValue: currentStep.rawValue + 1) else { return }
        currentStep = nextIndex
    }
    
    func previousStep() {
        guard let prevIndex = CheckoutStep(rawValue: currentStep.rawValue - 1) else { return }
        currentStep = prevIndex
    }
    
    @MainActor
    func submit() async {
        isSubmitting = true
        errorMessage = nil
        
        do {
            let formData = try buildMultipartFormData()
            let response: MessageResponse = try await apiClient.uploadMultipart(
                path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/inspect",
                formData: formData
            )
            isComplete = true
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }
        
        isSubmitting = false
    }
    
    private func buildMultipartFormData() throws -> MultipartFormData {
        var formData = MultipartFormData()
        
        for (index, photo) in capturedPhotos.enumerated() {
            guard let imageData = photo.jpegData(compressionQuality: 0.8) else { continue }
            formData.addFile(data: imageData, name: "photos[\(index)]", filename: "photo_\(index).jpg", mimeType: "image/jpeg")
        }
        
        if !conditionNotes.isEmpty {
            formData.addField(name: "condition_notes", value: conditionNotes)
        }
        
        if let signature = signatureImage, let pngData = signature.pngData() {
            let base64String = pngData.base64EncodedString()
            formData.addField(name: "signature_data", value: base64String)
        }
        
        return formData
    }
}
```

#### View: CheckoutInspectionView

```swift
struct CheckoutInspectionView: View {
    @Bindable var viewModel: CheckoutInspectionViewModel
    @Environment(\.dismiss) private var dismiss
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Step indicator
                StepProgressBar(
                    steps: CheckoutInspectionViewModel.CheckoutStep.allCases.map(\.title),
                    currentStep: viewModel.currentStep.rawValue
                )
                .padding()
                
                // Step content
                TabView(selection: $viewModel.currentStep) {
                    PhotoCaptureStep(photos: $viewModel.capturedPhotos)
                        .tag(CheckoutInspectionViewModel.CheckoutStep.photos)
                    
                    ConditionNotesStep(notes: $viewModel.conditionNotes)
                        .tag(CheckoutInspectionViewModel.CheckoutStep.notes)
                    
                    SignatureStep(
                        signatureImage: $viewModel.signatureImage,
                        agreementText: viewModel.agreementText
                    )
                        .tag(CheckoutInspectionViewModel.CheckoutStep.signature)
                    
                    ReviewStep(viewModel: viewModel)
                        .tag(CheckoutInspectionViewModel.CheckoutStep.review)
                }
                .tabViewStyle(.page(indexDisplayMode: .never))
                .animation(.easeInOut, value: viewModel.currentStep)
                
                // Navigation buttons
                HStack {
                    if viewModel.currentStep.rawValue > 0 {
                        Button("Back") { viewModel.previousStep() }
                            .buttonStyle(.bordered)
                    }
                    Spacer()
                    if viewModel.currentStep == .review {
                        Button("Submit") { Task { await viewModel.submit() } }
                            .buttonStyle(.borderedProminent)
                            .tint(CKTheme.accent)
                            .disabled(!viewModel.canSubmit)
                    } else {
                        Button("Next") { viewModel.nextStep() }
                            .buttonStyle(.borderedProminent)
                            .tint(CKTheme.accent)
                            .disabled(viewModel.currentStep == .photos && viewModel.capturedPhotos.isEmpty)
                    }
                }
                .padding()
            }
            .navigationTitle("Checkout Inspection")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
            }
            .alert("Error", isPresented: .init(
                get: { viewModel.errorMessage != nil },
                set: { if !$0 { viewModel.errorMessage = nil } }
            )) {
                Button("OK") { viewModel.errorMessage = nil }
            } message: {
                Text(viewModel.errorMessage ?? "")
            }
            .onChange(of: viewModel.isComplete) { _, complete in
                if complete { dismiss() }
            }
        }
    }
}
```

#### Multipart Upload Extension on APIClient

```swift
// Extension on APIClient for multipart form data uploads
extension APIClient {
    @MainActor
    func uploadMultipart<T: Decodable>(path: String, formData: MultipartFormData) async throws -> T {
        let boundary = formData.boundary
        var request = URLRequest(url: baseURL.appendingPathComponent("/api\(path)"))
        request.httpMethod = "POST"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        
        if let token = authManager.token {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        
        request.httpBody = formData.build()
        
        let (data, response) = try await session.data(for: request)
        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }
        try await handleStatusCode(httpResponse.statusCode, data: data)
        return try decoder.decode(T.self, from: data)
    }
}
```

#### MultipartFormData Helper

```swift
struct MultipartFormData {
    let boundary = "Boundary-\(UUID().uuidString)"
    private var body = Data()
    
    mutating func addField(name: String, value: String) {
        body.append("--\(boundary)\r\n")
        body.append("Content-Disposition: form-data; name=\"\(name)\"\r\n\r\n")
        body.append("\(value)\r\n")
    }
    
    mutating func addFile(data: Data, name: String, filename: String, mimeType: String) {
        body.append("--\(boundary)\r\n")
        body.append("Content-Disposition: form-data; name=\"\(name)\"; filename=\"\(filename)\"\r\n")
        body.append("Content-Type: \(mimeType)\r\n\r\n")
        body.append(data)
        body.append("\r\n")
    }
    
    func build() -> Data {
        var result = body
        result.append("--\(boundary)--\r\n")
        return result
    }
}

private extension Data {
    mutating func append(_ string: String) {
        if let data = string.data(using: .utf8) {
            append(data)
        }
    }
}
```

---

### 16. iOS ReturnInspectionView (Requirement 17)

#### ViewModel: ReturnInspectionViewModel

```swift
@Observable
final class ReturnInspectionViewModel {
    var currentStep: ReturnStep = .photos
    var capturedPhotos: [UIImage] = []
    var conditionNotes: String = ""
    var damageFlagged: Bool = false
    
    var isSubmitting = false
    var errorMessage: String? = nil
    var isComplete = false
    
    private let apiClient: APIClient
    private let bookingId: Int
    private let orderId: Int
    
    enum ReturnStep: Int, CaseIterable {
        case photos = 0
        case notes = 1
        case damage = 2
        case review = 3
        
        var title: String {
            switch self {
            case .photos: return "Photos"
            case .notes: return "Condition Notes"
            case .damage: return "Damage Check"
            case .review: return "Review & Submit"
            }
        }
    }
    
    init(apiClient: APIClient, bookingId: Int, orderId: Int) {
        self.apiClient = apiClient
        self.bookingId = bookingId
        self.orderId = orderId
    }
    
    var canSubmit: Bool {
        !capturedPhotos.isEmpty && !isSubmitting
    }
    
    func nextStep() {
        guard let next = ReturnStep(rawValue: currentStep.rawValue + 1) else { return }
        currentStep = next
    }
    
    func previousStep() {
        guard let prev = ReturnStep(rawValue: currentStep.rawValue - 1) else { return }
        currentStep = prev
    }
    
    @MainActor
    func submit() async {
        isSubmitting = true
        errorMessage = nil
        
        do {
            var formData = MultipartFormData()
            
            for (index, photo) in capturedPhotos.enumerated() {
                guard let imageData = photo.jpegData(compressionQuality: 0.8) else { continue }
                formData.addFile(data: imageData, name: "photos[\(index)]", filename: "photo_\(index).jpg", mimeType: "image/jpeg")
            }
            
            if !conditionNotes.isEmpty {
                formData.addField(name: "condition_notes", value: conditionNotes)
            }
            
            formData.addField(name: "damage_flagged", value: damageFlagged ? "1" : "0")
            
            let response: MessageResponse = try await apiClient.uploadMultipart(
                path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/inspect",
                formData: formData
            )
            isComplete = true
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }
        
        isSubmitting = false
    }
}
```

#### View: ReturnInspectionView

```swift
struct ReturnInspectionView: View {
    @Bindable var viewModel: ReturnInspectionViewModel
    @Environment(\.dismiss) private var dismiss
    
    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                StepProgressBar(
                    steps: ReturnInspectionViewModel.ReturnStep.allCases.map(\.title),
                    currentStep: viewModel.currentStep.rawValue
                )
                .padding()
                
                TabView(selection: $viewModel.currentStep) {
                    PhotoCaptureStep(photos: $viewModel.capturedPhotos)
                        .tag(ReturnInspectionViewModel.ReturnStep.photos)
                    
                    ConditionNotesStep(notes: $viewModel.conditionNotes)
                        .tag(ReturnInspectionViewModel.ReturnStep.notes)
                    
                    DamageFlagStep(damageFlagged: $viewModel.damageFlagged)
                        .tag(ReturnInspectionViewModel.ReturnStep.damage)
                    
                    ReturnReviewStep(viewModel: viewModel)
                        .tag(ReturnInspectionViewModel.ReturnStep.review)
                }
                .tabViewStyle(.page(indexDisplayMode: .never))
                .animation(.easeInOut, value: viewModel.currentStep)
                
                HStack {
                    if viewModel.currentStep.rawValue > 0 {
                        Button("Back") { viewModel.previousStep() }
                            .buttonStyle(.bordered)
                    }
                    Spacer()
                    if viewModel.currentStep == .review {
                        Button("Submit") { Task { await viewModel.submit() } }
                            .buttonStyle(.borderedProminent)
                            .tint(CKTheme.accent)
                            .disabled(!viewModel.canSubmit)
                    } else {
                        Button("Next") { viewModel.nextStep() }
                            .buttonStyle(.borderedProminent)
                            .tint(CKTheme.accent)
                            .disabled(viewModel.currentStep == .photos && viewModel.capturedPhotos.isEmpty)
                    }
                }
                .padding()
            }
            .navigationTitle("Return Inspection")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
            }
            .alert("Error", isPresented: .init(
                get: { viewModel.errorMessage != nil },
                set: { if !$0 { viewModel.errorMessage = nil } }
            )) {
                Button("OK") { viewModel.errorMessage = nil }
            } message: {
                Text(viewModel.errorMessage ?? "")
            }
            .onChange(of: viewModel.isComplete) { _, complete in
                if complete { dismiss() }
            }
        }
    }
}
```

#### DamageFlagStep View

```swift
struct DamageFlagStep: View {
    @Binding var damageFlagged: Bool
    
    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            Text("Damage Assessment")
                .font(CKTypography.title3)
                .foregroundStyle(CKTheme.textPrimary)
            
            Text("Toggle this on if the equipment has returned with damage.")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            
            Toggle("Damage Flagged", isOn: $damageFlagged)
                .tint(CKTheme.error)
                .padding()
                .background(CKTheme.backgroundCard)
                .clipShape(RoundedRectangle(cornerRadius: 12))
            
            if damageFlagged {
                HStack(spacing: 8) {
                    Image(systemName: "exclamationmark.triangle.fill")
                        .foregroundStyle(CKTheme.warning)
                    Text("Flagged assets will be marked as \"In Repair\" upon submission.")
                        .font(CKTypography.callout)
                        .foregroundStyle(CKTheme.warning)
                }
                .padding()
                .background(CKTheme.warning.opacity(0.1))
                .clipShape(RoundedRectangle(cornerRadius: 8))
            }
            
            Spacer()
        }
        .padding()
    }
}
```

---

### 17. iOS SignatureView (Requirement 19)

A Canvas-based finger drawing view that exports the drawn content as a base64-encoded PNG.

```swift
import SwiftUI

struct SignatureView: View {
    @Binding var signatureImage: UIImage?
    
    @State private var lines: [[CGPoint]] = []
    @State private var currentLine: [CGPoint] = []
    
    var body: some View {
        VStack(spacing: 12) {
            Text("Sign below")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
            
            Canvas { context, size in
                for line in lines {
                    drawLine(context: &context, points: line)
                }
                if !currentLine.isEmpty {
                    drawLine(context: &context, points: currentLine)
                }
            }
            .frame(height: 200)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .overlay(
                RoundedRectangle(cornerRadius: 12)
                    .stroke(CKTheme.textTertiary.opacity(0.3), lineWidth: 1)
            )
            .gesture(
                DragGesture(minimumDistance: 0)
                    .onChanged { value in
                        currentLine.append(value.location)
                    }
                    .onEnded { _ in
                        lines.append(currentLine)
                        currentLine = []
                        exportSignature()
                    }
            )
            
            HStack {
                Button("Clear") {
                    lines = []
                    currentLine = []
                    signatureImage = nil
                }
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.error)
                
                Spacer()
                
                if signatureImage != nil {
                    Label("Captured", systemImage: "checkmark.circle.fill")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.success)
                }
            }
        }
    }
    
    private func drawLine(context: inout GraphicsContext, points: [CGPoint]) {
        guard points.count > 1 else { return }
        var path = Path()
        path.move(to: points[0])
        for point in points.dropFirst() {
            path.addLine(to: point)
        }
        context.stroke(path, with: .color(.black), lineWidth: 2.5)
    }
    
    @MainActor
    private func exportSignature() {
        let renderer = ImageRenderer(content:
            Canvas { context, size in
                for line in lines {
                    drawLine(context: &context, points: line)
                }
            }
            .frame(width: 400, height: 200)
            .background(Color.white)
        )
        renderer.scale = 2.0 // Retina
        signatureImage = renderer.uiImage
    }
}
```

#### SignatureStep (wrapper used in CheckoutInspectionView)

```swift
struct SignatureStep: View {
    @Binding var signatureImage: UIImage?
    let agreementText: String?
    
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                Text("Customer Signature")
                    .font(CKTypography.title3)
                    .foregroundStyle(CKTheme.textPrimary)
                
                Text("This step is optional. Have the customer sign below to acknowledge the equipment handover.")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
                
                if let agreement = agreementText, !agreement.isEmpty {
                    VStack(alignment: .leading, spacing: 8) {
                        Text("Rental Agreement")
                            .font(CKTypography.headline)
                            .foregroundStyle(CKTheme.textPrimary)
                        
                        Text(agreement)
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                            .padding()
                            .background(CKTheme.backgroundCard)
                            .clipShape(RoundedRectangle(cornerRadius: 8))
                    }
                }
                
                SignatureView(signatureImage: $signatureImage)
            }
            .padding()
        }
    }
}
```

---

### 18. iOS Stage Action Buttons on BookingDetailView (Requirement 18)

#### Stage Action Configuration

```swift
enum StageAction: Identifiable {
    case advanceToPacking
    case markReady
    case checkOut
    case markReturned
    case returnInspection
    case completeInspection
    
    var id: String {
        switch self {
        case .advanceToPacking: return "advance_packing"
        case .markReady: return "mark_ready"
        case .checkOut: return "check_out"
        case .markReturned: return "mark_returned"
        case .returnInspection: return "return_inspection"
        case .completeInspection: return "complete_inspection"
        }
    }
    
    var label: String {
        switch self {
        case .advanceToPacking: return "Advance to Packing"
        case .markReady: return "Mark Ready"
        case .checkOut: return "Check Out"
        case .markReturned: return "Mark Returned"
        case .returnInspection: return "Return Inspection"
        case .completeInspection: return "Complete Inspection"
        }
    }
    
    var icon: String {
        switch self {
        case .advanceToPacking: return "shippingbox"
        case .markReady: return "checkmark.circle"
        case .checkOut: return "person.fill.checkmark"
        case .markReturned: return "arrow.uturn.backward"
        case .returnInspection: return "camera.fill"
        case .completeInspection: return "camera.fill"
        }
    }
    
    var isPrimary: Bool {
        switch self {
        case .advanceToPacking, .markReady, .checkOut, .returnInspection, .completeInspection: return true
        case .markReturned: return false
        }
    }
    
    /// Whether this action launches an inspection flow sheet (vs. a simple API call)
    var launchesSheet: Bool {
        switch self {
        case .checkOut, .returnInspection, .completeInspection: return true
        default: return false
        }
    }
    
    /// Derive available actions from the current fulfilment_stage
    static func actions(for stage: String) -> [StageAction] {
        switch stage {
        case "ordered": return [.advanceToPacking]
        case "packing": return [.markReady]
        case "ready": return [.checkOut]
        case "checked_out": return [.markReturned, .returnInspection]
        case "returned": return [.completeInspection]
        case "inspected": return []
        default: return []
        }
    }
}
```

#### Integration into BookingDetailView

Add a new section to the existing `BookingDetailView`:

```swift
// New section added to the content() List
private func stageActionsSection(_ booking: BookingDetail) -> some View {
    let actions = StageAction.actions(for: booking.fulfilmentStage)
    
    return Group {
        if !actions.isEmpty {
            Section {
                ForEach(actions) { action in
                    Button {
                        handleAction(action, booking: booking)
                    } label: {
                        Label(action.label, systemImage: action.icon)
                            .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(action.isPrimary ? CKTheme.accent : CKTheme.textSecondary)
                    .controlSize(.large)
                }
            } header: {
                Text("Actions")
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textSecondary)
            }
            .listRowBackground(CKTheme.backgroundCard)
        }
    }
}
```

For simple advance actions (non-sheet), the view model calls the advance-stage endpoint:

```swift
@MainActor
private func advanceStage() async {
    guard let booking else { return }
    isAdvancing = true
    
    do {
        let response: MessageResponse = try await apiClient.request(
            Endpoint(
                method: .post,
                path: "/admin/shop/orders/\(booking.orderId ?? 0)/bookings/\(booking.id)/advance-stage"
            )
        )
        await loadBooking() // Refresh to get updated stage
    } catch let error as APIError {
        errorMessage = error.errorDescription
    } catch {
        errorMessage = "An unexpected error occurred."
    }
    
    isAdvancing = false
}
```

---

### 19. iOS FulfilmentStageIndicator (Requirement 20)

A visual progress component showing all fulfilment stages with colours and SF Symbol icons.

#### Stage Configuration

```swift
struct StageConfig {
    let stage: String
    let label: String
    let color: Color
    let icon: String
    
    static let allStages: [StageConfig] = [
        StageConfig(stage: "ordered", label: "Ordered", color: .gray, icon: "clock"),
        StageConfig(stage: "packing", label: "Packing", color: .blue, icon: "shippingbox"),
        StageConfig(stage: "ready", label: "Ready", color: .green, icon: "checkmark.circle"),
        StageConfig(stage: "checked_out", label: "Checked Out", color: .teal, icon: "person.fill.checkmark"),
        StageConfig(stage: "returned", label: "Returned", color: .orange, icon: "arrow.uturn.backward"),
        StageConfig(stage: "inspected", label: "Inspected", color: .green, icon: "checkmark.seal.fill"),
    ]
    
    static func indexOf(_ stage: String) -> Int {
        allStages.firstIndex(where: { $0.stage == stage }) ?? 0
    }
}
```

#### FulfilmentStageIndicator View

```swift
struct FulfilmentStageIndicator: View {
    let currentStage: String
    
    private var currentIndex: Int {
        StageConfig.indexOf(currentStage)
    }
    
    var body: some View {
        HStack(spacing: 4) {
            ForEach(Array(StageConfig.allStages.enumerated()), id: \.element.stage) { index, config in
                stageItem(config: config, index: index)
                
                if index < StageConfig.allStages.count - 1 {
                    connector(completed: index < currentIndex)
                }
            }
        }
        .padding(.vertical, 8)
    }
    
    @ViewBuilder
    private func stageItem(config: StageConfig, index: Int) -> some View {
        VStack(spacing: 4) {
            ZStack {
                Circle()
                    .fill(backgroundColor(for: index))
                    .frame(width: 32, height: 32)
                
                Image(systemName: iconName(config: config, index: index))
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(iconColor(for: index))
            }
            
            Text(config.label)
                .font(.system(size: 9, weight: index == currentIndex ? .semibold : .regular))
                .foregroundStyle(labelColor(for: index))
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
    }
    
    private func connector(completed: Bool) -> some View {
        Rectangle()
            .fill(completed ? Color.green.opacity(0.6) : Color.gray.opacity(0.2))
            .frame(height: 2)
            .frame(maxWidth: 12)
            .offset(y: -8) // Align with circle center
    }
    
    // MARK: - Styling Helpers
    
    private func backgroundColor(for index: Int) -> Color {
        let config = StageConfig.allStages[index]
        if index == currentIndex {
            return config.color.opacity(0.2)
        } else if index < currentIndex {
            return config.color.opacity(0.1)
        } else {
            return Color.gray.opacity(0.08)
        }
    }
    
    private func iconName(config: StageConfig, index: Int) -> String {
        if index < currentIndex {
            return "checkmark" // Completed stages show checkmark overlay
        }
        return config.icon
    }
    
    private func iconColor(for index: Int) -> Color {
        let config = StageConfig.allStages[index]
        if index == currentIndex {
            return config.color // Full opacity for current
        } else if index < currentIndex {
            return config.color.opacity(0.7) // Reduced opacity for completed
        } else {
            return Color.gray.opacity(0.4) // Muted grey for future
        }
    }
    
    private func labelColor(for index: Int) -> Color {
        if index == currentIndex {
            return CKTheme.textPrimary
        } else if index < currentIndex {
            return CKTheme.textSecondary
        } else {
            return CKTheme.textTertiary
        }
    }
}
```

#### Integration into BookingDetailView

The `FulfilmentStageIndicator` is placed at the top of the booking info section:

```swift
private func bookingInfoSection(_ booking: BookingDetail) -> some View {
    Section {
        // Stage indicator at top
        FulfilmentStageIndicator(currentStage: booking.fulfilmentStage)
            .listRowInsets(EdgeInsets(top: 12, leading: 8, bottom: 12, trailing: 8))
        
        // ... existing rows (product, customer, dates, etc.)
    } header: {
        Text("Booking Info")
            .font(CKTypography.callout)
            .foregroundStyle(CKTheme.textSecondary)
    }
    .listRowBackground(CKTheme.backgroundCard)
}
```

Since the view reads `booking.fulfilmentStage` directly and the booking is held as `@State`, the indicator updates reactively when `loadBooking()` refreshes the data after a stage advance.

---

### 20. Shared iOS Components

#### PhotoCaptureStep

```swift
import PhotosUI

struct PhotoCaptureStep: View {
    @Binding var photos: [UIImage]
    @State private var showCamera = false
    @State private var selectedItems: [PhotosPickerItem] = []
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Capture Photos")
                .font(CKTypography.title3)
                .foregroundStyle(CKTheme.textPrimary)
            
            Text("Take at least one photo of the equipment condition.")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            
            // Photo grid
            if !photos.isEmpty {
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 80))], spacing: 8) {
                    ForEach(photos.indices, id: \.self) { index in
                        ZStack(alignment: .topTrailing) {
                            Image(uiImage: photos[index])
                                .resizable()
                                .scaledToFill()
                                .frame(width: 80, height: 80)
                                .clipShape(RoundedRectangle(cornerRadius: 8))
                            
                            Button {
                                photos.remove(at: index)
                            } label: {
                                Image(systemName: "xmark.circle.fill")
                                    .foregroundStyle(.white, .red)
                            }
                            .offset(x: 4, y: -4)
                        }
                    }
                }
            }
            
            // Capture buttons
            HStack(spacing: 12) {
                Button {
                    showCamera = true
                } label: {
                    Label("Camera", systemImage: "camera.fill")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .disabled(photos.count >= 10)
                
                PhotosPicker(
                    selection: $selectedItems,
                    maxSelectionCount: 10 - photos.count,
                    matching: .images
                ) {
                    Label("Library", systemImage: "photo.on.rectangle")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .disabled(photos.count >= 10)
            }
            
            Text("\(photos.count)/10 photos")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textTertiary)
            
            Spacer()
        }
        .padding()
        .fullScreenCover(isPresented: $showCamera) {
            CameraView(capturedImage: .init(
                get: { nil },
                set: { image in
                    if let image { photos.append(image) }
                }
            ))
        }
        .onChange(of: selectedItems) { _, newItems in
            Task {
                for item in newItems {
                    if let data = try? await item.loadTransferable(type: Data.self),
                       let image = UIImage(data: data) {
                        photos.append(image)
                    }
                }
                selectedItems = []
            }
        }
    }
}
```

#### ConditionNotesStep

```swift
struct ConditionNotesStep: View {
    @Binding var notes: String
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Condition Notes")
                .font(CKTypography.title3)
                .foregroundStyle(CKTheme.textPrimary)
            
            Text("Optionally describe the equipment condition.")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            
            TextEditor(text: $notes)
                .frame(minHeight: 120)
                .padding(8)
                .background(CKTheme.backgroundCard)
                .clipShape(RoundedRectangle(cornerRadius: 12))
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(CKTheme.textTertiary.opacity(0.3), lineWidth: 1)
                )
            
            Text("\(notes.count)/2000")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textTertiary)
            
            Spacer()
        }
        .padding()
    }
}
```

#### StepProgressBar

```swift
struct StepProgressBar: View {
    let steps: [String]
    let currentStep: Int
    
    var body: some View {
        HStack(spacing: 0) {
            ForEach(steps.indices, id: \.self) { index in
                HStack(spacing: 4) {
                    Circle()
                        .fill(stepColor(index))
                        .frame(width: 24, height: 24)
                        .overlay {
                            if index < currentStep {
                                Image(systemName: "checkmark")
                                    .font(.system(size: 11, weight: .bold))
                                    .foregroundStyle(.white)
                            } else {
                                Text("\(index + 1)")
                                    .font(.system(size: 11, weight: .semibold))
                                    .foregroundStyle(index == currentStep ? .white : CKTheme.textTertiary)
                            }
                        }
                    
                    if index < steps.count - 1 {
                        Rectangle()
                            .fill(index < currentStep ? CKTheme.accent : Color.gray.opacity(0.2))
                            .frame(height: 2)
                            .frame(maxWidth: .infinity)
                    }
                }
            }
        }
    }
    
    private func stepColor(_ index: Int) -> Color {
        if index < currentStep { return CKTheme.success }
        if index == currentStep { return CKTheme.accent }
        return Color.gray.opacity(0.2)
    }
}
```

---

## Error Handling (Requirements 15–20)

| Scenario | Handling |
|----------|----------|
| Invalid stage transition (skip/backward) | FulfilmentStageService throws InvalidArgumentException with descriptive message |
| Pre-condition unmet for stage advance | FulfilmentStageService throws InvalidArgumentException listing unmet conditions |
| Checkout inspection submit fails (network) | iOS displays alert, retains captured photos/notes/signature for retry |
| Return inspection submit fails (network) | iOS displays alert, retains captured photos/notes/damage flag for retry |
| Signature data exceeds size limit | Laravel validation rejects with 422; iOS shows validation error |
| Invalid signature_data format | Backend stores as-is (text field); admin renders directly as base64 src |
| Advance stage API 422 error | iOS displays alert with error message from response |
| Camera permission denied | iOS shows system settings prompt (standard PhotosUI behaviour) |
| Photo library permission denied | iOS shows limited photos picker (PHPicker, no full library access needed) |

---

## File Structure (Requirements 15–20)

```
ios/CKAdmin/CKAdmin/
├── Models/
│   └── BookingDetail.swift         (existing — no changes needed, already has fulfilmentStage)
├── Services/
│   ├── APIClient.swift             (extended: uploadMultipart method)
│   └── MultipartFormData.swift     (new: multipart form data builder)
├── Views/
│   └── Rentals/
│       ├── BookingDetailView.swift  (extended: stage actions section, stage indicator)
│       ├── Inspections/
│       │   ├── CheckoutInspectionView.swift       (new)
│       │   ├── CheckoutInspectionViewModel.swift  (new)
│       │   ├── ReturnInspectionView.swift         (new)
│       │   ├── ReturnInspectionViewModel.swift    (new)
│       │   ├── PhotoCaptureStep.swift             (new)
│       │   ├── ConditionNotesStep.swift           (new)
│       │   ├── DamageFlagStep.swift               (new)
│       │   ├── SignatureStep.swift                 (new)
│       │   └── SignatureView.swift                 (new)
│       └── Components/
│           ├── FulfilmentStageIndicator.swift      (new)
│           ├── StageAction.swift                   (new)
│           └── StepProgressBar.swift               (new)
├── DesignSystem/
│   └── Components/
│       └── CameraView.swift        (new: UIImagePickerController wrapper)

app/Http/Controllers/Api/Admin/
└── ShopOrderController.php         (extended: signature_data validation + storage in inspect)

resources/views/admin/shop/orders/
└── show.blade.php                  (extended: signature image display in inspection section)
```

---

## Correctness Properties (Requirements 15–20)

### Property 16: Sequential stage transition enforcement

*For any* Booking at fulfilment stage index N (0-based in the sequence [ordered, packing, ready, checked_out, returned, inspected]), attempting to advance to any stage other than the stage at index N+1 must be rejected with an InvalidArgumentException.

**Validates: Requirements 15.11**

### Property 17: Stage action button derivation from fulfilment stage

*For any* valid fulfilment_stage value, the set of available stage actions must exactly match the defined mapping: "ordered" → [Advance to Packing], "packing" → [Mark Ready], "ready" → [Check Out], "checked_out" → [Mark Returned, Return Inspection], "returned" → [Complete Inspection], "inspected" → [].

**Validates: Requirements 16.1, 16.9, 17.1, 17.10, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6**

### Property 18: Inspection data retention on error

*For any* checkout or return inspection submission that results in an API error response, the captured data (photos, condition notes, signature/damage flag) must remain intact in the view model and not be cleared.

**Validates: Requirements 16.8, 17.9**

### Property 19: Signature data round-trip persistence

*For any* valid base64-encoded PNG string submitted as `signature_data` in the inspect endpoint request, after successful processing the Booking's `signature_data` field must contain the same base64 string. When `signature_data` is absent or null in the request, the Booking's `signature_data` field must remain null.

**Validates: Requirements 19.4, 19.5, 19.8**

### Property 20: Stage indicator styling correctness

*For any* Booking at fulfilment stage index N (0-based in the sequence [ordered, packing, ready, checked_out, returned, inspected]), the FulfilmentStageIndicator must render: stages at indices 0..N-1 with "completed" styling (checkmark icon, reduced opacity colour), the stage at index N with "active" styling (full opacity, assigned colour and icon), and stages at indices N+1..5 with "future" styling (muted grey).

**Validates: Requirements 20.2, 20.3, 20.4, 20.5**

### Property 21: Parallel state track validity

*For any* Booking, the `status` field must always contain a value from the set {confirmed, active, returned, cancelled} and the `fulfilment_stage` field must always contain a value from the set {ordered, packing, ready, checked_out, returned, inspected}.

**Validates: Requirements 15.1**

### Property 22: Checkout inspection advances to checked_out

*For any* Booking at fulfilment_stage "ready", when a checkout inspection is submitted successfully (photos captured), the Booking's fulfilment_stage must transition to "checked_out".

**Validates: Requirements 15.7, 16.7**

### Property 23: Return inspection advances to inspected

*For any* Booking at fulfilment_stage "returned" (or "checked_out" which auto-advances through "returned"), when a return inspection is submitted successfully, the Booking's fulfilment_stage must transition to "inspected".

**Validates: Requirements 15.9, 17.8**

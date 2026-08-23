@if($answers->isNotEmpty())
    <div class="mt-4 border-t pt-3">
        <h5 class="text-sm font-medium text-gray-600 mb-2">Your Responses</h5>
        <dl class="grid grid-cols-1 gap-2">
            @foreach($answers as $answer)
                <div>
                    <dt class="text-xs text-gray-500">{{ $answer->question_label }}</dt>
                    <dd class="text-sm text-gray-900">{{ $answer->answer_value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif

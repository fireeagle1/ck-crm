@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Approval Required — {{ $project->title }}
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    @if ($approval->type === 'Document Approval')
        <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
            Your approval has been requested on a document for the project <strong>{{ $project->title }}</strong>.
        </p>

        @if ($approval->document)
            <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
                <p style="margin:0 0 4px; font-size:12px; color:#6b7280;">Document</p>
                <p style="margin:0; font-size:14px; font-weight:600; color:#1f2937;">{{ $approval->document->label }}</p>
                <p style="margin:4px 0 0; font-size:13px; color:#6b7280;">Type: {{ $approval->document->document_type }}</p>
            </div>
        @endif
    @else
        <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
            Your approval has been requested to mark the project <strong>{{ $project->title }}</strong> as complete. Please review the project and confirm that all work has been delivered to your satisfaction.
        </p>
    @endif

    <a href="{{ $portalLink }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        Review &amp; Respond
    </a>
@endsection

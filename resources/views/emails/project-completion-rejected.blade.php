@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Completion Rejected — {{ $project->title }}
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        The customer has rejected the completion request for the project <strong>{{ $project->title }}</strong>. The project has been moved back to its previous status.
    </p>

    <div style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:16px; margin:0 0 16px;">
        <p style="margin:0 0 4px; font-size:12px; font-weight:600; color:#991b1b;">Rejection Reason</p>
        <p style="margin:0; font-size:14px; color:#1f2937; line-height:1.6; white-space:pre-wrap;">{{ $rejectionReason }}</p>
    </div>

    <a href="{{ route('admin.projects.show', $project) }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        View Project
    </a>
@endsection

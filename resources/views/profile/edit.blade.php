@extends('layouts.app')

@section('content')
<section class="shell">

    @if(session('success'))
        <div class="alert-banner success">
            <i class="bi bi-check-circle fs-4"></i>
            <div>
                <strong>{{ session('success') }}</strong>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert-banner warning">
            <i class="bi bi-exclamation-triangle fs-4"></i>
            <div>
                <strong>Required fields missing</strong>
                <p class="mb-0">{{ session('warning') }}</p>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-section">
                    <p class="section-label mb-1">Basic</p>
                    <h4 class="mb-3">Profile snapshot</h4>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" value="{{ $user->name }}" readonly>
                            <div class="form-text">Contact an administrator to change your name.</div>
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="{{ $user->email }}" readonly>
                            <div class="form-text">Contact an administrator to change your email.</div>
                        </div>
                    </div>
                </div>

                @if($profileFields->count() > 0)
                    <div class="form-section">
                        <p class="section-label mb-1">Custom</p>
                        <h4 class="mb-3">Additional information</h4>
                        <div class="field-grid">
                            @foreach($profileFields as $field)
                                @php
                                    $currentValue = $user->getProfileValue($field->short_name);
                                    $fieldName = 'profile_' . $field->short_name;
                                @endphp
                                <div class="form-group">
                                    <label for="{{ $fieldName }}" class="form-label fw-semibold">
                                        {{ $field->name }}
                                        @if($field->required)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    @if($field->input_type === 'text')
                                        <input type="text" class="form-control @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" value="{{ old($fieldName, $currentValue) }}" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->input_type === 'number')
                                        <input type="number" class="form-control @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" value="{{ old($fieldName, $currentValue) }}" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->input_type === 'textarea')
                                        <textarea class="form-control @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" rows="3" {{ $field->required ? 'required' : '' }}>{{ old($fieldName, $currentValue) }}</textarea>
                                    @elseif($field->input_type === 'select')
                                        <select class="form-select @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" {{ $field->required ? 'required' : '' }}>
                                            @if(!$field->required)
                                                <option value="">-- Select an option --</option>
                                            @endif
                                            @foreach($field->options as $option)
                                                <option value="{{ $option }}" {{ old($fieldName, $currentValue) === $option ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field->input_type === 'select_multiple')
                                        <select class="form-select @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}[]" multiple {{ $field->required ? 'required' : '' }}>
                                            @foreach($field->options as $option)
                                                <option value="{{ $option }}" {{ in_array($option, old($fieldName, $currentValue ?: [])) ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Hold Ctrl/Cmd to select multiple options.</div>
                                    @endif

                                    @if($field->description)
                                        <div class="form-text">{{ $field->description }}</div>
                                    @endif

                                    @error($fieldName)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="empty-state">
                        <i class="bi bi-info-circle"></i>
                        <h5 class="mt-3">No additional fields</h5>
                        <p class="text-muted mb-0">Administrators have not configured extra profile questions yet.</p>
                    </div>
                @endif

                @if($profileFields->count() > 0)
                    <div class="form-actions">
                        <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dark"><i class="bi bi-check-circle"></i> Update profile</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</section>
@endsection

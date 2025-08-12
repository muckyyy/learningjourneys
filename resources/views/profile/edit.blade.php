@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-pencil"></i> Edit Profile</h2>
                <a href="{{ route('profile.show') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Required Fields Missing:</strong><br>
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-person-circle"></i> Update Your Profile Information
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information (Read-only) -->
                        <h5 class="text-primary mb-3">Basic Information</h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" value="{{ $user->name }}" readonly>
                                    <div class="form-text">Contact an administrator to change your name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="{{ $user->email }}" readonly>
                                    <div class="form-text">Contact an administrator to change your email</div>
                                </div>
                            </div>
                        </div>

                        @if($profileFields->count() > 0)
                            <!-- Custom Profile Fields -->
                            <h5 class="text-primary mb-3">Additional Information</h5>
                            <div class="row">
                                @foreach($profileFields as $field)
                                    @php
                                        $currentValue = $user->getProfileValue($field->short_name);
                                        $fieldName = 'profile_' . $field->short_name;
                                    @endphp
                                    
                                    <div class="col-md-{{ in_array($field->input_type, ['textarea']) ? '12' : '6' }} mb-3">
                                        <label for="{{ $fieldName }}" class="form-label">
                                            {{ $field->name }}
                                            @if($field->required)
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>
                                        
                                        @if($field->input_type === 'text')
                                            <input type="text" 
                                                   class="form-control @error($fieldName) is-invalid @enderror" 
                                                   id="{{ $fieldName }}" 
                                                   name="{{ $fieldName }}" 
                                                   value="{{ old($fieldName, $currentValue) }}"
                                                   {{ $field->required ? 'required' : '' }}>
                                                   
                                        @elseif($field->input_type === 'number')
                                            <input type="number" 
                                                   class="form-control @error($fieldName) is-invalid @enderror" 
                                                   id="{{ $fieldName }}" 
                                                   name="{{ $fieldName }}" 
                                                   value="{{ old($fieldName, $currentValue) }}"
                                                   {{ $field->required ? 'required' : '' }}>
                                                   
                                        @elseif($field->input_type === 'textarea')
                                            <textarea class="form-control @error($fieldName) is-invalid @enderror" 
                                                      id="{{ $fieldName }}" 
                                                      name="{{ $fieldName }}" 
                                                      rows="3"
                                                      {{ $field->required ? 'required' : '' }}>{{ old($fieldName, $currentValue) }}</textarea>
                                                      
                                        @elseif($field->input_type === 'select')
                                            <select class="form-select @error($fieldName) is-invalid @enderror" 
                                                    id="{{ $fieldName }}" 
                                                    name="{{ $fieldName }}"
                                                    {{ $field->required ? 'required' : '' }}>
                                                @if(!$field->required)
                                                    <option value="">-- Select an option --</option>
                                                @endif
                                                @foreach($field->options as $option)
                                                    <option value="{{ $option }}" 
                                                        {{ old($fieldName, $currentValue) === $option ? 'selected' : '' }}>
                                                        {{ $option }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            
                                        @elseif($field->input_type === 'select_multiple')
                                            <select class="form-select @error($fieldName) is-invalid @enderror" 
                                                    id="{{ $fieldName }}" 
                                                    name="{{ $fieldName }}[]" 
                                                    multiple
                                                    {{ $field->required ? 'required' : '' }}>
                                                @foreach($field->options as $option)
                                                    <option value="{{ $option }}" 
                                                        {{ in_array($option, old($fieldName, $currentValue ?: [])) ? 'selected' : '' }}>
                                                        {{ $option }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Hold Ctrl/Cmd to select multiple options</div>
                                        @endif
                                        
                                        @if($field->description)
                                            <div class="form-text">{{ $field->description }}</div>
                                        @endif
                                        
                                        @error($fieldName)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="bi bi-info-circle fs-1 text-muted"></i>
                                <h5 class="mt-3">No Additional Fields</h5>
                                <p class="text-muted">No custom profile fields have been configured yet.</p>
                            </div>
                        @endif

                        @if($profileFields->count() > 0)
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('profile.show') }}" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Profile
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<div class="tipowerup-installer-onboarding">
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                {{-- Logo --}}
                @if($this->logoDataUri)
                    <div class="text-center mb-4">
                        <img src="{{ $this->logoDataUri }}" alt="TI PowerUp" style="max-width: 220px; height: auto;">
                    </div>
                @endif

                {{-- Progress Indicator --}}
                <div class="mb-4">
                    <div class="d-flex align-items-start position-relative">
                        {{-- Progress Line --}}
                        <div class="position-absolute" style="z-index: 0; top: 24px; left: 25%; right: 25%; height: 2px; background-color: #dee2e6;"></div>
                        <div class="position-absolute" style="z-index: 0; top: 24px; left: 25%; height: 2px; background-color: #4a7cff;
                            width: {{ $currentStep === 1 ? '0%' : ($currentStep === 2 ? '25%' : '50%') }};
                            transition: width 0.3s ease;"></div>

                        {{-- Step 1 --}}
                        <div class="d-flex flex-column align-items-center position-relative flex-fill" style="z-index: 1;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                                {{ $currentStep >= 1 ? 'bg-primary text-white' : 'bg-white text-muted' }}"
                                 style="width: 48px; height: 48px; font-weight: 600;">
                                @if($currentStep > 1)
                                    <i class="fa fa-check"></i>
                                @else
                                    1
                                @endif
                            </div>
                            <small class="{{ $currentStep >= 1 ? 'text-dark fw-semibold' : 'text-muted' }}">Health Check</small>
                        </div>

                        {{-- Step 2 --}}
                        <div class="d-flex flex-column align-items-center position-relative flex-fill" style="z-index: 1;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                                {{ $currentStep >= 2 ? 'bg-primary text-white' : 'bg-white text-muted' }}"
                                 style="width: 48px; height: 48px; font-weight: 600;">
                                @if($currentStep > 2)
                                    <i class="fa fa-check"></i>
                                @else
                                    2
                                @endif
                            </div>
                            <small class="{{ $currentStep >= 2 ? 'text-dark fw-semibold' : 'text-muted' }}">API Key</small>
                        </div>

                        {{-- Step 3 --}}
                        <div class="d-flex flex-column align-items-center position-relative flex-fill" style="z-index: 1;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                                {{ $currentStep >= 3 ? 'bg-primary text-white' : 'bg-white text-muted' }}"
                                 style="width: 48px; height: 48px; font-weight: 600;">
                                3
                            </div>
                            <small class="{{ $currentStep >= 3 ? 'text-dark fw-semibold' : 'text-muted' }}">Welcome</small>
                        </div>
                    </div>
                </div>

                {{-- Step Content --}}
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        @if($currentStep === 1)
                            {{-- Step 1: System Health Check --}}
                            <h5 class="mb-2">{{ lang('tipowerup.installer::default.onboarding_step_health') }}</h5>
                            <p class="text-muted mb-3 small">
                                {{ lang('tipowerup.installer::default.onboarding_health_description') }}
                            </p>

                            {{-- Health Checks List --}}
                            <div class="mb-3">
                                @foreach($healthChecks as $check)
                                    <div class="d-flex align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                        <div class="me-2">
                                            @if($check['passed'])
                                                <i class="fa fa-check-circle text-success"></i>
                                            @else
                                                <i class="fa fa-times-circle text-danger"></i>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-medium small">{{ $check['label'] }}</span>
                                                <span class="text-muted small">{{ $check['message'] }}</span>
                                            </div>
                                            @if(!$check['passed'] && $check['fix'])
                                                <div class="alert alert-{{ $check['critical'] ? 'danger' : 'warning' }} mt-1 mb-0 py-1 px-2 small">
                                                    <strong>Fix:</strong> {{ $check['fix'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Community Links --}}
                            <div class="mt-3 pt-2 border-top">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="text-muted small">Need Help?</span>
                                    @foreach($communityLinks as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                           class="btn btn-outline-secondary btn-sm py-0 px-2">
                                            <i class="fa fa-external-link-alt me-1"></i>
                                            {{ $link['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Next Button --}}
                            <div class="mt-3">
                                <button wire:click="proceedToApiKey" type="button"
                                        class="btn btn-primary w-100"
                                        @if(!$this->canProceedFromHealth()) disabled @endif>
                                    Next: Enter API Key
                                    <i class="fa fa-arrow-right ms-2"></i>
                                </button>
                            </div>

                        @elseif($currentStep === 2)
                            {{-- Step 2: Enter API Key --}}
                            <h5 class="mb-2">{{ lang('tipowerup.installer::default.onboarding_step_api_key') }}</h5>
                            <p class="text-muted mb-3 small">
                                {{ lang('tipowerup.installer::default.onboarding_api_key_description') }}
                            </p>

                            {{-- API Key Input --}}
                            <div class="mb-3">
                                <label for="apiKey" class="form-label small">API Key</label>
                                <input wire:model.defer="apiKey" type="text" class="form-control form-control-lg"
                                       id="apiKey" placeholder="PUK-XXXX-XXXX-XXXX-XXXX" @if($isVerifying) disabled @endif>
                                <div class="form-text">
                                    {{ lang('tipowerup.installer::default.onboarding_api_key_help') }}
                                </div>
                            </div>

                            {{-- Error Message --}}
                            @if($errorMessage)
                                <div class="alert alert-danger py-2 small" role="alert">
                                    <i class="fa fa-exclamation-circle me-2"></i>
                                    {{ $errorMessage }}
                                </div>
                            @endif

                            {{-- Verify Button --}}
                            <div class="d-flex gap-2 mt-3">
                                <button wire:click="backToHealth" type="button" class="btn btn-outline-secondary"
                                        @if($isVerifying) disabled @endif>
                                    <i class="fa fa-arrow-left me-1"></i>
                                    Back
                                </button>
                                <button wire:click="verifyApiKey" type="button"
                                        class="btn btn-primary flex-grow-1"
                                        @if($isVerifying) disabled @endif>
                                    <span wire:loading.remove wire:target="verifyApiKey">
                                        <i class="fa fa-key me-1"></i>
                                        {{ lang('tipowerup.installer::default.action_verify_key') }}
                                    </span>
                                    <span wire:loading wire:target="verifyApiKey">
                                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                        Verifying...
                                    </span>
                                </button>
                            </div>

                        @elseif($currentStep === 3)
                            {{-- Step 3: Welcome --}}
                            <div class="text-center">
                                @if($userProfile && !empty($userProfile['avatar']))
                                    <img src="{{ $userProfile['avatar'] }}" alt="{{ $userProfile['name'] }}"
                                         class="rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                                         style="width: 80px; height: 80px; font-size: 32px; font-weight: 600;">
                                        {{ substr($userProfile['name'] ?? 'U', 0, 1) }}
                                    </div>
                                @endif

                                <h4 class="mb-2">
                                    {{ lang('tipowerup.installer::default.onboarding_welcome_message', ['name' => $userProfile['name'] ?? 'User']) }}
                                </h4>

                                @if($userProfile && !empty($userProfile['email']))
                                    <p class="text-muted mb-4">{{ $userProfile['email'] }}</p>
                                @endif

                                <div class="bg-light rounded p-3 mb-4">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <i class="fa fa-cog text-primary me-2"></i>
                                        <span class="small">
                                            <strong>Auto-detected install method:</strong>
                                            @if($detectedMethod === 'composer')
                                                Composer Installation
                                            @else
                                                Direct Extraction (Shared Hosting)
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <button wire:click="completeOnboarding" type="button"
                                            class="btn btn-primary btn-lg">
                                        <i class="fa fa-check me-2"></i>
                                        {{ lang('tipowerup.installer::default.onboarding_get_started') }}
                                    </button>

                                    <button wire:click="backToApiKey" type="button"
                                            class="btn btn-outline-secondary">
                                        <i class="fa fa-arrow-left me-1"></i>
                                        Back
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

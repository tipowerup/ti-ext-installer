<div class="tipowerup-installer-onboarding">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                {{-- Progress Indicator --}}
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center position-relative">
                        <div class="position-absolute top-50 start-0 end-0 border-top border-2"
                             style="z-index: 0; margin-top: -1px;"></div>

                        {{-- Step 1 --}}
                        <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                                {{ $currentStep >= 1 ? 'bg-primary text-white' : 'bg-light text-muted' }}"
                                 style="width: 48px; height: 48px; font-weight: 600;">
                                @if($currentStep > 1)
                                    <i class="fa fa-check"></i>
                                @else
                                    1
                                @endif
                            </div>
                            <small class="text-muted">Health Check</small>
                        </div>

                        {{-- Step 2 --}}
                        <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                                {{ $currentStep >= 2 ? 'bg-primary text-white' : 'bg-light text-muted' }}"
                                 style="width: 48px; height: 48px; font-weight: 600;">
                                @if($currentStep > 2)
                                    <i class="fa fa-check"></i>
                                @else
                                    2
                                @endif
                            </div>
                            <small class="text-muted">API Key</small>
                        </div>

                        {{-- Step 3 --}}
                        <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                                {{ $currentStep >= 3 ? 'bg-primary text-white' : 'bg-light text-muted' }}"
                                 style="width: 48px; height: 48px; font-weight: 600;">
                                3
                            </div>
                            <small class="text-muted">Welcome</small>
                        </div>
                    </div>
                </div>

                {{-- Step Content --}}
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        @if($currentStep === 1)
                            {{-- Step 1: System Health Check --}}
                            <h4 class="mb-3">{{ lang('tipowerup.installer::default.onboarding_step_health') }}</h4>
                            <p class="text-muted mb-4">
                                {{ lang('tipowerup.installer::default.onboarding_health_description') }}
                            </p>

                            {{-- Health Checks List --}}
                            <div class="mb-4">
                                @foreach($healthChecks as $check)
                                    <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                                        <div class="me-3">
                                            @if($check['passed'])
                                                <i class="fa fa-check-circle text-success" style="font-size: 24px;"></i>
                                            @else
                                                <i class="fa fa-times-circle text-danger" style="font-size: 24px;"></i>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">{{ $check['label'] }}</h6>
                                            <p class="mb-1 text-muted small">{{ $check['message'] }}</p>
                                            @if(!$check['passed'] && $check['fix'])
                                                <div class="alert alert-{{ $check['critical'] ? 'danger' : 'warning' }} mt-2 mb-0 py-2 px-3 small">
                                                    <strong>{{ lang('tipowerup.installer::default.health_fix_instructions') }}:</strong>
                                                    {{ $check['fix'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Missing Core Extensions Warning --}}
                            @if(count($missingCoreExtensions) > 0)
                                <div class="alert alert-danger" role="alert">
                                    <div class="d-flex align-items-start">
                                        <i class="fa fa-exclamation-triangle me-2 mt-1"></i>
                                        <div>
                                            <h6 class="mb-2">Missing Required TI Core Extensions</h6>
                                            <p class="mb-2">
                                                The following TI core extensions must be installed before using PowerUp Installer:
                                            </p>
                                            <ul class="mb-2">
                                                @foreach($missingCoreExtensions as $ext)
                                                    <li><strong>{{ $ext['name'] }}</strong> ({{ $ext['code'] }})</li>
                                                @endforeach
                                            </ul>
                                            <a href="{{ $missingCoreExtensions[0]['manage_url'] ?? '#' }}"
                                               class="btn btn-sm btn-danger">
                                                <i class="fa fa-external-link-alt me-1"></i>
                                                Manage Extensions
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Community Links --}}
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="mb-3 text-muted small">Need Help?</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($communityLinks as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fa fa-external-link-alt me-1"></i>
                                            {{ $link['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Next Button --}}
                            <div class="mt-4">
                                <button wire:click="proceedToApiKey" type="button"
                                        class="btn btn-primary btn-lg w-100"
                                        @if(!canProceedFromHealth()) disabled @endif>
                                    Next: Enter API Key
                                    <i class="fa fa-arrow-right ms-2"></i>
                                </button>
                            </div>

                        @elseif($currentStep === 2)
                            {{-- Step 2: Enter API Key --}}
                            <h4 class="mb-3">{{ lang('tipowerup.installer::default.onboarding_step_api_key') }}</h4>
                            <p class="text-muted mb-4">
                                {{ lang('tipowerup.installer::default.onboarding_api_key_description') }}
                            </p>

                            {{-- API Key Input --}}
                            <div class="mb-4">
                                <label for="apiKey" class="form-label">API Key</label>
                                <input wire:model.defer="apiKey" type="text" class="form-control form-control-lg"
                                       id="apiKey" placeholder="pk_live_..." @if($isVerifying) disabled @endif>
                                <div class="form-text">
                                    {{ lang('tipowerup.installer::default.onboarding_api_key_help') }}
                                </div>
                            </div>

                            {{-- Error Message --}}
                            @if($errorMessage)
                                <div class="alert alert-danger" role="alert">
                                    <i class="fa fa-exclamation-circle me-2"></i>
                                    {{ $errorMessage }}
                                </div>
                            @endif

                            {{-- Verify Button --}}
                            <div class="d-flex gap-2 mt-4">
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

                                <div class="bg-light rounded p-4 mb-4">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <i class="fa fa-cog text-primary me-2"></i>
                                        <span>
                                            <strong>Auto-detected install method:</strong>
                                            @if($detectedMethod === 'composer')
                                                Composer Installation
                                            @else
                                                Direct Extraction (Shared Hosting)
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <div class="d-flex flex-column gap-3">
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

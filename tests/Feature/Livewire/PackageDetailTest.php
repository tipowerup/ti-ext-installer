<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tipowerup\Installer\Livewire\PackageDetail;
use Tipowerup\Installer\Services\PowerUpApiClient;

beforeEach(function (): void {
    config()->set('tipowerup.installer.api_url', 'https://api.tipowerup.com');
});

it('renders the component', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')
            ->andReturn([
                'code' => 'tipowerup/ti-ext-test',
                'name' => 'Test Package',
                'description' => 'A test package',
                'version' => '1.0.0',
                'author' => 'TI PowerUp',
                'type' => 'extension',
            ]);
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->assertStatus(200);
});

it('loads package details from API on mount', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')
            ->with('tipowerup/ti-ext-test')
            ->once()
            ->andReturn([
                'code' => 'tipowerup/ti-ext-test',
                'name' => 'Test Package',
                'description' => 'A test package',
                'version' => '2.0.0',
                'author' => 'TI PowerUp',
                'type' => 'extension',
            ]);
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->assertSet('packageData.name', 'Test Package')
        ->assertSet('packageData.version', '2.0.0')
        ->assertSet('isLoading', false);
});

it('uses initial data for local packages without API call', function (): void {
    Livewire::test(PackageDetail::class, [
        'packageCode' => 'tipowerup/ti-ext-test',
        'initialData' => [
            'local' => true,
            'name' => 'Local Package',
            'version' => '1.0.0',
            'type' => 'extension',
        ],
    ])
        ->assertSet('packageData.name', 'Local Package')
        ->assertSet('packageData.local', true)
        ->assertSet('isLoading', false);
});

it('uses initial data for bundle type without API call', function (): void {
    Livewire::test(PackageDetail::class, [
        'packageCode' => 'tipowerup/ti-ext-bundle',
        'initialData' => [
            'type' => 'bundle',
            'name' => 'Bundle Package',
            'version' => '1.0.0',
        ],
    ])
        ->assertSet('packageData.name', 'Bundle Package')
        ->assertSet('isLoading', false);
});

it('handles API error with fallback data', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')
            ->andThrow(new RuntimeException('API Error'));
    });

    Livewire::test(PackageDetail::class, [
        'packageCode' => 'tipowerup/ti-ext-test',
        'initialData' => [
            'name' => 'Fallback Name',
            'version' => '1.0.0',
        ],
    ])
        ->assertSet('packageData.name', 'Fallback Name')
        ->assertSet('errorMessage', null)
        ->assertSet('isLoading', false);
});

it('shows error when API fails with no fallback', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')
            ->andThrow(new RuntimeException('Something went wrong'));
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->assertSet('errorMessage', 'Something went wrong')
        ->assertSet('isLoading', false);
});

it('switchDetailTab changes active tab', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')->andReturn(['name' => 'Test']);
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->call('switchDetailTab', 'changelog')
        ->assertSet('activeDetailTab', 'changelog')
        ->call('switchDetailTab', 'screenshots')
        ->assertSet('activeDetailTab', 'screenshots');
});

it('switchDetailTab ignores invalid tab', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')->andReturn(['name' => 'Test']);
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->call('switchDetailTab', 'invalid-tab')
        ->assertSet('activeDetailTab', 'description');
});

it('closeDetail dispatches package-detail-closed event', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')->andReturn(['name' => 'Test']);
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->call('closeDetail')
        ->assertDispatched('package-detail-closed');
});

it('installPackage dispatches begin-install and closes detail', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')->andReturn([
            'name' => 'Test Package',
            'code' => 'tipowerup/ti-ext-test',
        ]);
    });

    Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test'])
        ->call('installPackage')
        ->assertDispatched('begin-install')
        ->assertDispatched('package-detail-closed');
});

it('sanitizes HTML in description', function (): void {
    $this->mock(PowerUpApiClient::class, function ($mock): void {
        $mock->shouldReceive('getPackageDetail')->andReturn([
            'name' => 'Test',
            'description' => '**bold** and <script>alert("xss")</script>',
        ]);
    });

    $component = Livewire::test(PackageDetail::class, ['packageCode' => 'tipowerup/ti-ext-test']);
    $html = $component->get('packageData.description_html');

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('<strong>bold</strong>');
});

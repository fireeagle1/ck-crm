<?php

namespace Tests\Unit\Rules;

use App\Http\Requests\Concerns\NormalisesDomain;
use App\Rules\ValidDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase as AppTestCase;

class ValidDomainTest extends AppTestCase
{
    private function passes(string $value): bool
    {
        return Validator::make(['domain_name' => $value], ['domain_name' => [new ValidDomain]])->passes();
    }

    public function test_passes_canonical_input(): void
    {
        $this->assertTrue($this->passes('example.com'));
    }

    public function test_passes_variant_input(): void
    {
        $this->assertTrue($this->passes('  WWW.Example.COM. '));
    }

    public function test_fails_invalid_input_with_field_error(): void
    {
        $v = Validator::make(['domain_name' => 'not a domain'], ['domain_name' => [new ValidDomain]]);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('domain_name'));
    }

    public function test_trait_merges_canonical_value(): void
    {
        $request = new class extends FormRequest {
            use NormalisesDomain;

            public function prepare(): void
            {
                $this->prepareForValidation();
            }
        };
        $request->replace(['domain_name' => 'WWW.Example.com.']);
        $request->prepare();
        $this->assertSame('example.com', $request->input('domain_name'));

        $bad = clone $request;
        $bad->replace(['domain_name' => 'not a domain']);
        $bad->prepare();
        $this->assertSame('not a domain', $bad->input('domain_name'));
    }
}

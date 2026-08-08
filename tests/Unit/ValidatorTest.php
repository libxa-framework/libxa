<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function test_required(): void
    {
        $this->assertTrue((new Validator([], ['name' => 'required']))->fails());
        $this->assertTrue((new Validator(['name' => ''], ['name' => 'required']))->fails());
        $this->assertTrue((new Validator(['name' => []], ['name' => 'required']))->fails());
        $this->assertTrue((new Validator(['name' => 'Ada'], ['name' => 'required']))->passes());
    }

    /**
     * filter_var('0', FILTER_VALIDATE_INT) is int(0) — falsy — so the old
     * `! filter_var(...)` check rejected a perfectly valid zero.
     */
    public function test_zero_is_a_valid_integer(): void
    {
        $this->assertTrue((new Validator(['qty' => '0'], ['qty' => 'integer']))->passes());
        $this->assertTrue((new Validator(['qty' => 0], ['qty' => 'integer']))->passes());
        $this->assertTrue((new Validator(['qty' => '-5'], ['qty' => 'integer']))->passes());
        $this->assertTrue((new Validator(['qty' => 'abc'], ['qty' => 'integer']))->fails());
    }

    /**
     * Form input arrives as strings, so `integer|min:18` used to compare
     * mb_strlen('20') === 2 against 18 and always failed.
     */
    public function test_min_and_max_compare_the_value_for_numeric_fields(): void
    {
        $this->assertTrue((new Validator(['age' => '20'], ['age' => 'integer|min:18']))->passes());
        $this->assertTrue((new Validator(['age' => '16'], ['age' => 'integer|min:18']))->fails());
        $this->assertTrue((new Validator(['age' => '150'], ['age' => 'integer|max:120']))->fails());
    }

    public function test_min_and_max_compare_length_for_string_fields(): void
    {
        $this->assertTrue((new Validator(['pw' => 'short'], ['pw' => 'string|min:8']))->fails());
        $this->assertTrue((new Validator(['pw' => 'longenough'], ['pw' => 'string|min:8']))->passes());
        $this->assertTrue((new Validator(['pw' => str_repeat('x', 300)], ['pw' => 'string|max:255']))->fails());
    }

    public function test_min_and_max_count_array_elements(): void
    {
        $this->assertTrue((new Validator(['tags' => ['a']], ['tags' => 'array|min:2']))->fails());
        $this->assertTrue((new Validator(['tags' => ['a', 'b']], ['tags' => 'array|min:2']))->passes());
    }

    public function test_email_and_url(): void
    {
        $this->assertTrue((new Validator(['e' => 'nope'], ['e' => 'email']))->fails());
        $this->assertTrue((new Validator(['e' => 'a@b.co'], ['e' => 'email']))->passes());
        $this->assertTrue((new Validator(['u' => 'notaurl'], ['u' => 'url']))->fails());
        $this->assertTrue((new Validator(['u' => 'https://a.co'], ['u' => 'url']))->passes());
    }

    public function test_confirmed(): void
    {
        $ok = new Validator(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'required|confirmed']
        );
        $this->assertTrue($ok->passes());

        $bad = new Validator(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'required|confirmed']
        );
        $this->assertTrue($bad->fails());
    }

    public function test_nullable_skips_other_rules(): void
    {
        $v = new Validator(['bio' => null], ['bio' => 'nullable|string|min:10']);

        $this->assertTrue($v->passes());
    }

    public function test_sometimes_skips_absent_fields(): void
    {
        $v = new Validator([], ['nickname' => 'sometimes|string|min:3']);

        $this->assertTrue($v->passes());
        $this->assertArrayNotHasKey('nickname', $v->validated());
    }

    /**
     * validated() used to contain `field => null` for every optional field
     * that was never submitted, so a mass update wrote nulls over real values.
     */
    public function test_validated_only_contains_submitted_fields(): void
    {
        $v = new Validator(['name' => 'Ada'], ['name' => 'required', 'bio' => 'nullable|string']);

        $this->assertSame(['name' => 'Ada'], $v->validated());
    }

    /** A parameterised rule missing its parameter must not blow up. */
    public function test_parameterised_rules_without_a_parameter_are_skipped(): void
    {
        foreach (['in', 'not_in', 'min', 'max', 'between', 'regex', 'same', 'starts_with'] as $rule) {
            $v = new Validator(['x' => 'value'], ['x' => $rule]);

            $this->assertTrue($v->passes(), "bare '{$rule}' should be a no-op, not a crash");
        }
    }

    public function test_between_without_a_comma_is_skipped(): void
    {
        $this->assertTrue((new Validator(['x' => 'val'], ['x' => 'between:3']))->passes());
    }

    public function test_in_and_not_in(): void
    {
        $this->assertTrue((new Validator(['role' => 'admin'], ['role' => 'in:admin,user']))->passes());
        $this->assertTrue((new Validator(['role' => 'root'], ['role' => 'in:admin,user']))->fails());
        $this->assertTrue((new Validator(['role' => 'root'], ['role' => 'not_in:root']))->fails());
    }

    public function test_rules_may_be_given_as_an_array(): void
    {
        $v = new Validator(['x' => 'abc'], ['x' => ['required', 'string', 'min:2']]);

        $this->assertTrue($v->passes());
    }

    public function test_error_bag_contents(): void
    {
        $v = new Validator([], ['email' => 'required|email', 'name' => 'required']);

        $this->assertTrue($v->fails());

        $errors = $v->errors();
        $this->assertTrue($errors->has('email'));
        $this->assertTrue($errors->has('name'));
        $this->assertStringContainsString('required', strtolower((string) $errors->first('email')));
    }

    public function test_custom_messages(): void
    {
        $v = new Validator(
            [],
            ['email' => 'required'],
            ['email.required' => 'We need your email.']
        );

        $this->assertSame('We need your email.', $v->errors()->first('email'));
    }

    /** A rule built from untrusted input must not become an injection point. */
    public function test_unique_rejects_a_non_identifier_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Validator(['email' => 'a@b.co'], ['email' => 'unique:users; DROP TABLE users--,email']);
    }

    public function test_boolean_rule(): void
    {
        foreach ([true, false, 1, 0, '1', '0', 'true', 'false'] as $value) {
            $this->assertTrue(
                (new Validator(['flag' => $value], ['flag' => 'boolean']))->passes(),
                var_export($value, true) . ' should be a valid boolean'
            );
        }

        $this->assertTrue((new Validator(['flag' => 'maybe'], ['flag' => 'boolean']))->fails());
    }
}

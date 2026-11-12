<?php

namespace Tests\Unit;

use App\Services\Ai\PhotoClassifier;
use App\Support\PhoneNumbers;
use Tests\TestCase;

/**
 * Verdict rules for store photos, using numbers seen on real Ciledug photos.
 * No database and no Gemini call: only the decision step is exercised.
 *
 *   official                                  -> ok
 *   foreign + PACKAGING                       -> ignored
 *   near                                      -> suspicious (scam if it looks pasted on)
 *   foreign + digits unclear                  -> suspicious
 *   foreign + unclear context (OTHER)         -> suspicious (scam if pasted on / promo photo)
 *   any other foreign number                  -> scam
 */
class PhotoVerdictTest extends TestCase
{
    /** Ciledug's registered numbers: telp "021 - 7312895/96" and whatsapp "087744413628". */
    protected function storeNumbers()
    {
        return PhoneNumbers::officialNumbers(['021 - 7312895/96', '087744413628']);
    }

    /** The company-wide complaints & suggestions line. */
    protected function companyNumbers()
    {
        return PhoneNumbers::officialNumbers(['0878 0816 3003']);
    }

    protected function verdict(array $phones, array $extra = [], $store = null, $company = null)
    {
        $data = array_merge(['phones' => $phones, 'qr' => false, 'handles' => [], 'category' => 'ADA_KONTAK'], $extra);

        return app(PhotoClassifier::class)->verdict($data, $store ?? $this->storeNumbers(), $company ?? $this->companyNumbers());
    }

    protected function phone($number, $role = 'SIGN', $edited = false, $clear = true)
    {
        return ['number' => $number, 'role' => $role, 'edited' => $edited, 'clear' => $clear];
    }

    // --- official / near ----------------------------------------------------------------

    public function test_store_numbers_and_the_company_line_are_ok()
    {
        $v = $this->verdict([$this->phone('021 7312895'), $this->phone('0877-4441-3628'), $this->phone('0878 0816 3003', 'COMPLAINT')]);

        $this->assertSame('ok', $v['label']);
        $this->assertFalse($v['phone_mismatch']);
        $this->assertSame(['official', 'official', 'official'], array_column($v['details'], 'status'));
        $this->assertSame(['store', 'store', 'company'], array_column($v['details'], 'source'));
    }

    public function test_a_number_one_digit_off_from_an_official_one_is_suspicious()
    {
        $v = $this->verdict([$this->phone('0878 0816 3000')]);

        $this->assertSame('suspicious', $v['label']);
        $this->assertTrue($v['phone_mismatch']);
        $this->assertSame('near', $v['details'][0]['status']);
        $this->assertSame('087808163003', $v['details'][0]['nearest']);
        $this->assertSame(1, $v['details'][0]['distance']);
    }

    public function test_a_near_number_that_looks_pasted_on_is_scam()
    {
        $v = $this->verdict([$this->phone('0878 0816 3000', 'OVERLAY', true)]);

        $this->assertSame('scam', $v['label']);
    }

    // --- foreign numbers ----------------------------------------------------------------

    /**
     * @dataProvider foreignNumbers
     */
    public function test_a_foreign_number_on_the_sign_the_complaints_line_or_a_pasted_banner_is_scam($number)
    {
        foreach (['SIGN', 'COMPLAINT', 'OVERLAY'] as $role) {
            $v = $this->verdict([$this->phone($number, $role)]);

            $this->assertSame('scam', $v['label'], "role {$role}");
            $this->assertSame('foreign', $v['details'][0]['status']);
            $this->assertTrue($v['phone_mismatch']);
            $this->assertStringContainsString('tidak terdaftar', $v['reason']);
        }
    }

    public function foreignNumbers()
    {
        return [
            'wa on the store banner' => ['0813-7092-436'],
            'seen as 0819 0814 0001' => ['0819-0814-0001'],
            'international format' => ['+62 89412244317'],
        ];
    }

    public function test_a_foreign_number_in_an_unclear_context_is_only_suspicious()
    {
        $v = $this->verdict([$this->phone('0812-8888-1234', 'OTHER')]);

        $this->assertSame('suspicious', $v['label']);
        $this->assertStringContainsString('konteksnya tidak jelas', $v['reason']);
    }

    public function test_an_unclear_context_becomes_scam_when_the_number_looks_pasted_on_or_the_photo_promotes_others()
    {
        $this->assertSame('scam', $this->verdict([$this->phone('0813-7092-436', 'OTHER', true)])['label']);
        $this->assertSame('scam', $this->verdict([$this->phone('0813-7092-436', 'OTHER')], ['category' => 'PROMOSI_ASING'])['label']);
    }

    public function test_a_foreign_number_with_unclear_digits_is_only_suspicious()
    {
        $this->assertSame('suspicious', $this->verdict([$this->phone('0813-7092-436', 'SIGN', false, false)])['label']);
        $this->assertSame('suspicious', $this->verdict([$this->phone('0813-7092-436', 'OVERLAY', true, false)])['label']);
    }

    // --- packaging exception ------------------------------------------------------------

    public function test_a_number_printed_on_packaging_is_ignored()
    {
        $v = $this->verdict([$this->phone('0800 1234 5678', 'PACKAGING')]);

        $this->assertSame('ok', $v['label']);
        $this->assertSame('ignored', $v['details'][0]['status']);
        $this->assertFalse($v['phone_mismatch']);
    }

    public function test_packaging_is_ignored_even_on_a_promo_photo_but_not_when_it_looks_pasted_on()
    {
        $this->assertSame('ignored', $this->verdict([$this->phone('0800 1234 5678', 'PACKAGING')], ['category' => 'PROMOSI_ASING'])['details'][0]['status']);
        // A number that looks pasted onto the packaging is judged like any other foreign number.
        $this->assertSame('scam', $this->verdict([$this->phone('0800 1234 5678', 'PACKAGING', true)])['label']);
    }

    // --- other cases --------------------------------------------------------------------

    public function test_a_store_without_registered_numbers_can_not_be_judged()
    {
        $v = $this->verdict([$this->phone('0813-7092-436')], [], [], $this->companyNumbers());

        $this->assertSame('suspicious', $v['label']);
        $this->assertSame(['0813-7092-436'], $v['flags']['unverifiable_phones']);
    }

    public function test_other_warning_signs_make_a_photo_suspicious()
    {
        $this->assertSame('suspicious', $this->verdict([], ['qr' => true])['label']);
        $this->assertSame('suspicious', $this->verdict([], ['category' => 'PROMOSI_ASING'])['label']);
        // Websites are not checked: a URL on a photo says nothing either way.
        $this->assertSame('ok', $this->verdict([], ['urls' => ['weldon.co.id', 'pinjaman-cepat.example']])['label']);
    }

    public function test_unreadable_and_plain_photos()
    {
        $this->assertSame('unreadable', $this->verdict([], ['category' => 'TIDAK_TERBACA'])['label']);
        $this->assertSame('ok', $this->verdict([], ['category' => 'FOTO_TOKO_NORMAL'])['label']);
    }

    public function test_gemini_short_keys_are_normalised()
    {
        $data = app(PhotoClassifier::class)->normalize([
            'p' => [['n' => '0813-7092-436', 'r' => 'SIGN', 'e' => false, 'c' => true]],
            'qr' => false, 's' => ['@toko.palsu'], 'cat' => 'CONTACT',
        ]);

        $this->assertSame('ADA_KONTAK', $data['category']);
        $this->assertSame(['@toko.palsu'], $data['handles']);
        $this->assertSame('0813-7092-436', $data['phones'][0]['number']);
        $this->assertTrue($data['phones'][0]['clear']);
    }

    public function test_nearest_ignores_short_numbers_and_exact_matches_are_not_near()
    {
        $official = $this->companyNumbers();

        $this->assertNull(PhoneNumbers::nearest('12345', $official));
        $this->assertNull(PhoneNumbers::near('0878 0816 3003', $official), 'identical is official, not near');
        $this->assertNull(PhoneNumbers::near('0878 0816 3999', $official), 'three digits apart is a different number');
        $this->assertSame(2, PhoneNumbers::near('0878 0816 3010', $official)[1]);
    }
}

<?php

namespace Tests\Unit\ImageSourcing;

use App\Services\ImageSourcing\Gtin;
use PHPUnit\Framework\TestCase;

class GtinTest extends TestCase
{
    public function test_it_accepts_real_barcodes(): void
    {
        $this->assertTrue(Gtin::isValid('3017620422003'));   // EAN-13
        $this->assertTrue(Gtin::isValid('4005808890590'));   // EAN-13
        $this->assertTrue(Gtin::isValid('96385074'));        // EAN-8
    }

    public function test_it_rejects_a_wrong_check_digit(): void
    {
        $this->assertFalse(Gtin::isValid('3017620422004'));
    }

    /**
     * Le cas qui compte pour ce projet : le champ « barcode » du catalogue
     * contient des références internes du logiciel de caisse, sur neuf
     * chiffres. Les prendre pour des codes-barres ferait consommer du quota
     * d'API pour rien, et pourrait apparier un produit au hasard.
     */
    public function test_it_rejects_the_internal_references_of_the_till_software(): void
    {
        $this->assertFalse(Gtin::isValid('000032536'));
        $this->assertFalse(Gtin::isValid('000021128'));
        $this->assertNull(Gtin::normalize('000032536'));
    }

    public function test_it_strips_separators_before_validating(): void
    {
        $this->assertSame('3017620422003', Gtin::normalize('3-017620-422003'));
    }

    public function test_it_rejects_empty_and_non_numeric_values(): void
    {
        $this->assertFalse(Gtin::isValid(null));
        $this->assertFalse(Gtin::isValid(''));
        $this->assertFalse(Gtin::isValid('SANS-CODE'));
    }
}

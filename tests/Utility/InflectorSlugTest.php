<?php
/**
 * NATAPHP – Inflector::slug() and Inflector::transliterate().
 */

namespace Nata\Test\Utility;

use Nata\Utility\Inflector;
use PHPUnit\Framework\TestCase;

/**
 * slug() kept letters outside its transliteration table, so names in other
 * scripts gave slugs that are not ASCII ("yeИdry"). With $ascii it gives plain
 * ASCII; without it, it behaves as before, so stored slugs do not change.
 */
class InflectorSlugTest extends TestCase {

/**
 * The default behaviour is unchanged: underscores, Latin accents folded,
 * other scripts kept.
 *
 * @return void
 */
    public function testDefaultSlugIsUnchanged(): void {
        $this->assertSame('antonio_azambujo', Inflector::slug('António Azambujo'));
        $this->assertSame('ca-la-nandu', Inflector::slug('Ça & Là — Ñandú', '-'));
        $this->assertSame('yeИdry', Inflector::slug('yeИdry', '-'));
        $this->assertSame('Teatro-Jordao', Inflector::slug('Teatro Jordão', '-', false));
    }

/**
 * With $ascii, every script ends up as ASCII letters, digits and the replacement.
 *
 * @return void
 */
    public function testAsciiSlugTransliteratesEveryScript(): void {
        $this->assertSame('yeidry', Inflector::slug('yeИdry', '-', true, true));
        $this->assertSame('moskva-2026', Inflector::slug('Москва 2026', '-', true, true));
        $this->assertSame('antonio-azambujo', Inflector::slug('António Azambujo', '-', true, true));
        $this->assertSame('cineteatro-municipal-d-joao-v-damaia', Inflector::slug('Cineteatro Municipal D. João V (Damaia)', '-', true, true));
        $this->assertSame('ze-amaro', Inflector::slug('ze_amaro', '-', true, true));
        $this->assertSame('aero_ost_ss', Inflector::slug('Ærø Øst ß', '_', true, true));
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', Inflector::slug('東京', '-', true, true));
    }

/**
 * With $ascii, runs of separators collapse and none is left at either end;
 * a text without letters or digits gives an empty slug.
 *
 * @return void
 */
    public function testAsciiSlugTrimsSeparators(): void {
        $this->assertSame('fun', Inflector::slug('Fun.', '-', true, true));
        $this->assertSame('a-b', Inflector::slug('  --a -- b--  ', '-', true, true));
        $this->assertSame('', Inflector::slug('— & —', '-', true, true));
        $this->assertSame('', Inflector::slug('', '-', true, true));
    }

/**
 * transliterate() keeps case and returns ASCII only.
 *
 * @return void
 */
    public function testTransliterateKeepsCaseAndReturnsAscii(): void {
        $this->assertSame('Moskva', Inflector::transliterate('Москва'));
        $this->assertSame('Sao Joao', Inflector::transliterate('São João'));
        $this->assertSame(1, preg_match('/^[\x00-\x7F]*$/', Inflector::transliterate('東京 · Ελλάδα')));
    }

}

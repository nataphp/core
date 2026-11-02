<?php
/**
 * NATAPHP – locale negotiation from the request's Accept-Language.
 */

namespace Nata\Test\I18n;

use Nata\Core\Configure;
use Nata\Http\Request;
use Nata\I18n\I18n;
use Nata\Routing\Router;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * I18n::locale() with no locale set yet, in a web request with Accept-Language:
 * it negotiated the locale through I18n::available(), which built the list with
 * the current locale and so called I18n::locale() again, recursing forever.
 */
class I18nHttpLocaleTest extends TestCase {

/**
 * Locale root with a "pt" folder, for the negotiation to find.
 *
 * @var string
 */
    private string $_localeRoot;

/**
 * Fresh I18n instance and a locale root with "pt".
 *
 * @return void
 */
    protected function setUp(): void {
        $this->_resetI18n();
        $this->_localeRoot = TMP . 'i18n-http-locale' . DS;
        if (!is_dir($this->_localeRoot . 'pt')) {
            mkdir($this->_localeRoot . 'pt', 0777, true);
        }
        Configure::write('I18n.localeRoots', [$this->_localeRoot]);
    }

/**
 * Removes the request and the I18n state the test left behind.
 *
 * @return void
 */
    protected function tearDown(): void {
        Router::popRequest();
        Configure::delete('I18n.localeRoots');
        $this->_resetI18n();
    }

/**
 * Drops the I18n singleton, so the next call starts with no locale and no
 * available list (I18n::clear() sets the list to [], not null).
 *
 * @return void
 */
    private function _resetI18n(): void {
        (new ReflectionProperty(I18n::class, '_instance'))->setValue(null, null);
    }

/**
 * Pushes a request that only answers its Accept-Language header.
 *
 * @param string $acceptLanguage Accept-Language header
 * @return void
 */
    private function _pushRequest(string $acceptLanguage): void {
        Router::setRequest(new class($acceptLanguage) extends Request {

            private string $_acceptLanguage;

            public function __construct(string $acceptLanguage) {
                $this->_acceptLanguage = $acceptLanguage;
            }

            public function header($name = null) {
                return strtolower((string) $name) === 'accept-language' ? $this->_acceptLanguage : null;
            }
        });
    }

/**
 * The first locale() call negotiates an available locale.
 *
 * @return void
 */
    public function testLocaleNegotiatesAcceptLanguageWithoutRecursion(): void {
        $this->_pushRequest('pt-PT,pt;q=0.9,en;q=0.8');

        $this->assertSame('pt', I18n::locale());
        $this->assertArrayHasKey('pt', I18n::available());
    }

/**
 * No accepted language is available: the default locale.
 *
 * @return void
 */
    public function testLocaleFallsBackToDefaultWhenNoneAvailable(): void {
        $this->_pushRequest('de-DE,de;q=0.9');

        $this->assertSame('en', I18n::locale());
        $this->assertArrayHasKey('en', I18n::available());
    }

/**
 * available() as the first call: the list includes the default locale.
 *
 * @return void
 */
    public function testAvailableBeforeLocaleIncludesTheDefault(): void {
        $this->_pushRequest('pt-PT');

        $available = I18n::available();

        $this->assertArrayHasKey('en', $available);
        $this->assertArrayHasKey('pt', $available);
        $this->assertSame('pt', I18n::locale());
    }
}

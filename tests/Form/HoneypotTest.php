<?php
/**
 * NATAPHP – Form\Honeypot.
 */

namespace Nata\Test\Form;

use InvalidArgumentException;
use Nata\Core\Configure;
use Nata\Form\Honeypot;
use PHPUnit\Framework\TestCase;

/**
 * Trap field + signed time token, with a fixed clock.
 */
class HoneypotTest extends TestCase {

/**
 * Current time of the fake clock.
 *
 * @var int
 */
    protected int $_now = 1_800_000_000;

/**
 * Leave no salt behind for the other tests.
 *
 * @return void
 */
    protected function tearDown(): void {
        Configure::delete('Security.salt');
    }

/**
 * Honeypot with a test secret and the fake clock.
 *
 * @param array $options Extra options.
 * @return \Nata\Form\Honeypot
 */
    protected function _honeypot(array $options = []): Honeypot {
        return new Honeypot($options + ['secret' => 'test-secret', 'clock' => fn () => $this->_now]);
    }

/**
 * Data a person sends: empty trap, token from a few seconds ago.
 *
 * @param \Nata\Form\Honeypot $honeypot Honeypot.
 * @return array Request data.
 */
    protected function _humanData(Honeypot $honeypot): array {
        $fields = $honeypot->fields();
        $this->_now += 10;
        return ['name' => 'Maria', $fields['field'] => '', $fields['tokenField'] => $fields['token']];
    }

/**
 * A person passes.
 *
 * @return void
 */
    public function testPersonPasses(): void {
        $honeypot = $this->_honeypot();
        $this->assertFalse($honeypot->isBot($this->_humanData($honeypot)));
        $this->assertNull($honeypot->reason());
    }

/**
 * A filled trap is a bot, whatever the token.
 *
 * @return void
 */
    public function testFilledTrapIsABot(): void {
        $honeypot = $this->_honeypot();
        $data = $this->_humanData($honeypot);
        $data['website'] = 'https://spam.example';

        $this->assertTrue($honeypot->isBot($data));
        $this->assertSame(Honeypot::REASON_FILLED, $honeypot->reason());

        $data['website'] = ['array'];
        $this->assertTrue($honeypot->isBot($data));
    }

/**
 * Missing, malformed, forged or foreign tokens are bots.
 *
 * @return void
 */
    public function testBadTokensAreBots(): void {
        $honeypot = $this->_honeypot();
        $data = $this->_humanData($honeypot);
        [$time] = explode('.', $data['_hp']);

        foreach ([null, '', 'abc', $time, $time . '.' . str_repeat('0', 64), ($time - 60) . '.' . substr($data['_hp'], strlen($time) + 1)] as $token) {
            $attempt = $data;
            if ($token === null) {
                unset($attempt['_hp']);
            } else {
                $attempt['_hp'] = $token;
            }
            $this->assertTrue($honeypot->isBot($attempt), var_export($token, true));
            $this->assertSame(Honeypot::REASON_TOKEN, $honeypot->reason());
        }

        // Signed with another secret, or for another field.
        $this->assertTrue($this->_honeypot(['secret' => 'other'])->isBot($data));
        $this->assertTrue($this->_honeypot(['field' => 'url'])->isBot($data + ['url' => '']));
    }

/**
 * A post faster than minSeconds is a bot; at minSeconds it passes.
 *
 * @return void
 */
    public function testTooFastIsABot(): void {
        $honeypot = $this->_honeypot(['minSeconds' => 5]);
        $fields = $honeypot->fields();
        $data = ['website' => '', '_hp' => $fields['token']];

        $this->_now += 4;
        $this->assertTrue($honeypot->isBot($data));
        $this->assertSame(Honeypot::REASON_TOO_FAST, $honeypot->reason());

        $this->_now += 1;
        $this->assertFalse($honeypot->isBot($data));
    }

/**
 * An old token still passes (a page left open for hours).
 *
 * @return void
 */
    public function testOldTokenPasses(): void {
        $honeypot = $this->_honeypot();
        $data = $this->_humanData($honeypot);
        $this->_now += 3 * 86400;

        $this->assertFalse($honeypot->isBot($data));
    }

/**
 * The markup has the trap (hidden, out of the tab order) and the token.
 *
 * @return void
 */
    public function testHtml(): void {
        $html = $this->_honeypot()->html();

        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertMatchesRegularExpression('/name="_hp" value="\d+\.[0-9a-f]{64}"/', $html);
        $this->assertStringNotContainsString('display:none', $html);
    }

/**
 * The secret defaults to Security.salt and is required.
 *
 * @return void
 */
    public function testSecret(): void {
        Configure::write('Security.salt', 'salt-of-the-app');
        $fromSalt = new Honeypot(['clock' => fn () => $this->_now]);
        $explicit = $this->_honeypot(['secret' => 'salt-of-the-app']);
        $this->assertSame($explicit->token(), $fromSalt->token());

        Configure::write('Security.salt', '');
        $this->expectException(InvalidArgumentException::class);
        new Honeypot();
    }

}

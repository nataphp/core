<?php
/**
 * NataPHP Framework
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Form;

use InvalidArgumentException;
use Nata\Core\Configure;

/**
 * Bot trap for public forms, without session or cookie.
 *
 * Two checks, both on the posted data:
 *
 * 1. a text field people never see (moved off screen, out of the tab order,
 *    `aria-hidden`) and bots fill in;
 * 2. a token with the time the form was shown, signed with HMAC: a post
 *    without a valid token, or sent less than `minSeconds` after the form was
 *    shown, is a bot.
 *
 * The token has no upper age: a person may leave the page open for hours, and
 * a bot that can fetch the page can always fetch a fresh token. Pair it with a
 * rate limit (`Nata\Cache\RateLimiter`).
 *
 * The secret defaults to `Security.salt`. The field and token live outside
 * the namespace of a Nata form: read them from the raw request data.
 *
 * ```php
 * // controller, when showing the form
 * $this->set('honeypot', (new Honeypot())->html());
 *
 * // controller, when receiving it
 * $honeypot = new Honeypot();
 * if ($honeypot->isBot($this->request->data())) {
 *     Log::write('debug', 'Proposal refused: ' . $honeypot->reason());
 *     // answer as if it had worked, so the bot learns nothing
 * }
 * ```
 */
class Honeypot {

/**
 * Reasons given by reason().
 */
    const REASON_FILLED = 'filled';
    const REASON_TOKEN = 'token';
    const REASON_TOO_FAST = 'too_fast';

/**
 * Name of the trap field. Something a bot wants to fill.
 *
 * @var string
 */
    protected string $_field;

/**
 * Name of the hidden token field.
 *
 * @var string
 */
    protected string $_tokenField;

/**
 * Minimum seconds between showing and posting the form.
 *
 * @var int
 */
    protected int $_minSeconds;

/**
 * HMAC secret.
 *
 * @var string
 */
    protected string $_secret;

/**
 * Current Unix time (replaceable in tests).
 *
 * @var callable(): int
 */
    protected $_clock;

/**
 * Why the last isBot() call returned true (null when it returned false).
 *
 * @var string|null
 */
    protected ?string $_reason = null;

/**
 * Constructor.
 *
 * Options: `field` (default "website"), `tokenField` (default "_hp"),
 * `minSeconds` (default 3), `secret` (default `Security.salt`) and `clock`
 * (callable returning the Unix time; default time()).
 *
 * @param array $options Options.
 * @throws \InvalidArgumentException Without a secret.
 */
    public function __construct(array $options = []) {
        $options += [
            'field' => 'website',
            'tokenField' => '_hp',
            'minSeconds' => 3,
            'secret' => Configure::read('Security.salt'),
            'clock' => null,
        ];
        if (!is_string($options['secret']) || $options['secret'] === '') {
            throw new InvalidArgumentException('Honeypot needs a secret: set Security.salt or pass the "secret" option.');
        }

        $this->_field = (string)$options['field'];
        $this->_tokenField = (string)$options['tokenField'];
        $this->_minSeconds = max(0, (int)$options['minSeconds']);
        $this->_secret = $options['secret'];
        $this->_clock = $options['clock'] ?? 'time';
    }

/**
 * A new token for a form being shown now.
 *
 * @return string "<timestamp>.<hmac>"
 */
    public function token(): string {
        $time = (string)(int)($this->_clock)();
        return $time . '.' . $this->_sign($time);
    }

/**
 * Names and token, for templates that write their own markup.
 *
 * @return array{field: string, tokenField: string, token: string}
 */
    public function fields(): array {
        return [
            'field' => $this->_field,
            'tokenField' => $this->_tokenField,
            'token' => $this->token(),
        ];
    }

/**
 * Markup of the trap field and the token, to print inside the <form>.
 * Hidden with inline CSS (no stylesheet needed), not with `display: none`,
 * which some bots skip.
 *
 * @param string $label Label of the trap field, read by nobody but bots.
 * @return string HTML.
 */
    public function html(string $label = 'Website'): string {
        $field = htmlspecialchars($this->_field, ENT_QUOTES, 'UTF-8');
        return '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">'
            . '<label for="hp-' . $field . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>'
            . '<input type="text" id="hp-' . $field . '" name="' . $field . '" value="" tabindex="-1" autocomplete="off">'
            . '</div>'
            . '<input type="hidden" name="' . htmlspecialchars($this->_tokenField, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

/**
 * Whether the posted data comes from a bot.
 *
 * @param array $data Raw request data (the trap and token are top-level keys).
 * @return bool True for a bot; reason() tells why.
 */
    public function isBot(array $data): bool {
        $this->_reason = null;

        $trap = $data[$this->_field] ?? '';
        if (!is_string($trap) || trim($trap) !== '') {
            return $this->_refuse(static::REASON_FILLED);
        }

        $token = $data[$this->_tokenField] ?? null;
        if (!is_string($token) || !preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $token, $parts)) {
            return $this->_refuse(static::REASON_TOKEN);
        }
        if (!hash_equals($this->_sign($parts[1]), $parts[2])) {
            return $this->_refuse(static::REASON_TOKEN);
        }

        $elapsed = (int)($this->_clock)() - (int)$parts[1];
        if ($elapsed < $this->_minSeconds) {
            return $this->_refuse(static::REASON_TOO_FAST);
        }

        return false;
    }

/**
 * Why the last isBot() said yes: "filled", "token" or "too_fast".
 *
 * @return string|null Reason, or null when it said no.
 */
    public function reason(): ?string {
        return $this->_reason;
    }

/**
 * HMAC of a timestamp, bound to the field names so a token of one form
 * does not pass another.
 *
 * @param string $time Timestamp.
 * @return string Hex HMAC-SHA256.
 */
    protected function _sign(string $time): string {
        return hash_hmac('sha256', 'honeypot|' . $this->_field . '|' . $this->_tokenField . '|' . $time, $this->_secret);
    }

/**
 * Record the reason and say "bot".
 *
 * @param string $reason Reason.
 * @return bool True.
 */
    protected function _refuse(string $reason): bool {
        $this->_reason = $reason;
        return true;
    }

}

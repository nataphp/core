<?php
/**
 * NATAPHP – length rules of the form elements with multibyte text.
 */

namespace Nata\Test\Form;

use Nata\Form\Form;
use PHPUnit\Framework\TestCase;

/**
 * Lengths are counted in characters, not bytes. Validation::maxLength() already
 * used mb_strlen(), but `length` used strlen(), the error messages reported the
 * byte count, and Input::getValueOutput() cut the value with substr(): a cut in
 * the middle of an accented letter is invalid UTF-8, which htmlspecialchars()
 * turns into '', so the field came back to the form empty.
 */
class ElementLengthTest extends TestCase {

/**
 * Build a submitted form with the given elements and data.
 *
 * @param array $elements Element configs.
 * @param array $data Submitted data.
 * @return \Nata\Form\Form
 */
    protected function _form(array $elements, array $data): Form {
        $form = new Form(['form' => 'test', 'submittedData' => $data]);
        $form->elements()->loadAll($elements);
        return $form;
    }

/**
 * A value with accents exactly at maxLength is valid and keeps all its
 * characters on the way back to the form.
 *
 * @return void
 */
    public function testMaxLengthCountsCharacters(): void {
        // "Guimarães" is 9 characters and 10 bytes.
        $form = $this->_form([['type' => 'text', 'name' => 'city', 'label' => 'Cidade', 'maxLength' => 9]], ['city' => 'Guimarães']);

        $this->assertTrue($form->isValid());
        $this->assertSame('Guimarães', $form->elements()->get('city')->getValueOutput());
    }

/**
 * A value over maxLength is refused, and the message reports characters.
 *
 * @return void
 */
    public function testMaxLengthErrorReportsCharacters(): void {
        // 10 characters, 12 bytes.
        $form = $this->_form([['type' => 'text', 'name' => 'city', 'label' => 'Cidade', 'maxLength' => 9]], ['city' => 'Guimarãess']);

        $this->assertFalse($form->isValid());
        $error = (string)$form->elements()->get('city')->error();
        $this->assertStringContainsString('10', $error);
        $this->assertStringNotContainsString('11', $error);
    }

/**
 * The output cut at maxLength (a longer stored value shown in an edit form)
 * is valid UTF-8 and never empty.
 *
 * @return void
 */
    public function testValueOutputCutsWholeCharacters(): void {
        // The 4th character is "ç": cutting at 4 bytes would split it.
        $form = $this->_form([['type' => 'text', 'name' => 'word', 'label' => 'Palavra', 'maxLength' => 4]], ['word' => 'Açores']);

        $this->assertSame('Açor', $form->elements()->get('word')->getValueOutput());
    }

/**
 * `length` compares characters.
 *
 * @return void
 */
    public function testExactLengthCountsCharacters(): void {
        $valid = $this->_form([['type' => 'text', 'name' => 'code', 'label' => 'Código', 'length' => 4]], ['code' => 'ação']);
        $this->assertTrue($valid->isValid());

        $invalid = $this->_form([['type' => 'text', 'name' => 'code', 'label' => 'Código', 'length' => 4]], ['code' => 'açõe5']);
        $this->assertFalse($invalid->isValid());
    }

/**
 * minLength and between report characters too.
 *
 * @return void
 */
    public function testMinLengthAndBetweenReportCharacters(): void {
        // 3 characters, 6 bytes.
        $form = $this->_form([
            ['type' => 'text', 'name' => 'a', 'label' => 'A', 'minLength' => 4],
            ['type' => 'text', 'name' => 'b', 'label' => 'B', 'between' => [4, 8]],
        ], ['a' => 'ãõé', 'b' => 'ãõé']);

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString(' 3 ', (string)$form->elements()->get('a')->error());
        $this->assertStringNotContainsString('6', (string)$form->elements()->get('a')->error());
        $this->assertStringContainsString('3', (string)$form->elements()->get('b')->error());
        $this->assertStringNotContainsString('6', (string)$form->elements()->get('b')->error());
    }

}

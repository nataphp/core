<?php
/**
 * NATAPHP – Form::allowFiles().
 */

namespace Nata\Test\Form;

use Nata\Form\Form;
use PHPUnit\Framework\TestCase;

/**
 * Form::isValid() refuses any request carrying $_FILES, because the Upload
 * element sends its files to the storage on its own. A plain multipart form
 * whose controller reads its file inputs turns that off with `allowFiles`,
 * instead of emptying $_FILES before validating.
 */
class FormAllowFilesTest extends TestCase {

/**
 * A request with one uploaded file.
 *
 * @return void
 */
    protected function setUp(): void {
        $_FILES = ['cover' => [
            'name' => 'capa.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-upload',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ]];
    }

/**
 * Clean up.
 *
 * @return void
 */
    protected function tearDown(): void {
        $_FILES = [];
    }

/**
 * Build a submitted, otherwise valid form.
 *
 * @param array $config Form config.
 * @return \Nata\Form\Form
 */
    protected function _form(array $config = []): Form {
        $form = new Form($config + ['form' => 'test', 'submittedData' => ['title' => 'Auto da Barca']]);
        $form->elements()->loadAll([['type' => 'text', 'name' => 'title', 'label' => 'Título', 'required' => true]]);
        return $form;
    }

/**
 * By default the behaviour is unchanged: files make the form invalid.
 *
 * @return void
 */
    public function testFilesAreRefusedByDefault(): void {
        $form = $this->_form();

        $this->assertFalse($form->allowFiles());
        $this->assertFalse($form->isValid());
    }

/**
 * With the config option the form validates and is multipart.
 *
 * @return void
 */
    public function testConfigOptionAcceptsFiles(): void {
        // An explicit action: attributes() would otherwise read the request.
        $form = $this->_form(['allowFiles' => true, 'action' => '/espetaculos']);

        $this->assertTrue($form->allowFiles());
        $this->assertTrue($form->isValid());
        $this->assertSame('multipart/form-data', $form->attributes()->get('enctype'));
    }

/**
 * The setter does the same (for forms that set it in initialize()).
 *
 * @return void
 */
    public function testSetterAcceptsFiles(): void {
        $form = $this->_form();
        $this->assertSame($form, $form->allowFiles(true));

        $this->assertTrue($form->isValid());
    }

/**
 * Accepting files does not skip the validation of the fields.
 *
 * @return void
 */
    public function testFieldsAreStillValidated(): void {
        $form = new Form(['form' => 'test', 'allowFiles' => true, 'submittedData' => ['title' => '']]);
        $form->elements()->loadAll([['type' => 'text', 'name' => 'title', 'label' => 'Título', 'required' => true]]);

        $this->assertFalse($form->isValid());
    }

/**
 * Without files the option changes nothing.
 *
 * @return void
 */
    public function testWithoutFilesNothingChanges(): void {
        $_FILES = [];

        $this->assertTrue($this->_form()->isValid());
        $this->assertTrue($this->_form(['allowFiles' => true])->isValid());
    }

}

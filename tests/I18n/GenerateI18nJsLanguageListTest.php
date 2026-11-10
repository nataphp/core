<?php
/**
 * NATAPHP – GenerateI18nJs::writeLanguageList().
 */

namespace Nata\Test\I18n;

use Nata\Command\GenerateI18nJs;
use PHPUnit\Framework\TestCase;

/**
 * The list of dictionaries (public/i18n/languages.json) that i18n.js reads to
 * request only a dictionary that exists: a page in "pt-PT" with only "pt.js"
 * gets "pt.js", without a 404 for "pt-PT.js".
 */
class GenerateI18nJsLanguageListTest extends TestCase {

/**
 * A folder of this test's own, removed afterwards.
 *
 * @var string
 */
    protected string $_directory;

/**
 * A fresh folder.
 *
 * @return void
 */
    protected function setUp(): void {
        $this->_directory = TMP . 'i18n-list-' . bin2hex(random_bytes(4));
        mkdir($this->_directory, 0755, true);
    }

/**
 * Remove the folder and what this test wrote in it.
 *
 * @return void
 */
    protected function tearDown(): void {
        foreach (glob($this->_directory . DS . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->_directory);
    }

/**
 * Every dictionary of the folder, sorted; other files left out.
 *
 * @return void
 */
    public function testListsEveryDictionary(): void {
        foreach (['pt.js', 'en.js', 'pt-BR.js', 'notes.txt', 'nata.min.js'] as $file) {
            file_put_contents($this->_directory . DS . $file, '');
        }

        $this->assertSame(['en', 'pt', 'pt-BR'], GenerateI18nJs::writeLanguageList($this->_directory . DS));
        $this->assertSame('["en","pt","pt-BR"]', file_get_contents($this->_directory . DS . 'languages.json'));
    }

/**
 * An empty folder gives an empty list; a missing one, nothing.
 *
 * @return void
 */
    public function testEmptyAndMissingFolders(): void {
        $this->assertSame([], GenerateI18nJs::writeLanguageList($this->_directory));
        $this->assertSame('[]', file_get_contents($this->_directory . DS . 'languages.json'));
        $this->assertNull(GenerateI18nJs::writeLanguageList($this->_directory . DS . 'missing'));
    }

}

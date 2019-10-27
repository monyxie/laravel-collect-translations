<?php

namespace Monyxie\CollectTranslation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class TranslationCollect extends Command {
    const JSON_GROUP = '_json';
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'translation:collect
                            {--G|exclude-group=* : Translation groups to exclude}
                            {--l|locale=zh_cn : Locale to process}
                            {--r|regenerate : Whether the translation files should be regenerated. WARNING: ALL COMMENTS IN YOUR TRANSLATION FILES WILL BE LOST}
                            {--sort} : Whether the newly generated translation items should be sorted
                            {--y|yes} : Answer all confirmation questions with YES';
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translation:collect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';
    /**
     * @var array|null
     */
    private $excluded;
    private $locale;

    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle() {
        $this->locale = $this->option('locale') ?? 'zh_cn';

        $defined = $this->findDefined();
        $used = $this->findUsed();

        $useful = [];
        foreach ($used as $group => $items) {
            if (!isset($defined[$group])) {
                $this->comment("Missing group：$group");
            }

            foreach ($items as $item => $value) {
                if (!isset($defined[$group][$item])) {
                    $this->comment("Missing item：$group.$item");

                    $useful[$group][$item] = "$group.$item";
                }
            }
        }

        foreach ($defined as $group => $items) {
            if (!isset($used[$group])) {
                $this->comment("Unused group：$group");
            }
            foreach ($items as $item => $value) {
                if (!isset($used[$group][$item])) {
                    $this->comment("Unused item：$group.$item");
                } else {
                    $useful[$group][$item] = $value;
                }
            }
        }

        if ($this->option('regenerate')) {
            $languagePath = $this->getLanguagePath();
            if ($this->option('yes') || $this->confirm("Overwrite translation files under $languagePath ?")) {

                if (!file_exists($languagePath)) {
                    mkdir($languagePath, 0777, true);
                }

                foreach ($useful as $group => $items) {
                    $file = $languagePath . '/' . $group . '.php';

                    if ($this->option('sort')) {
                        ksort($items);
                    }
                    $this->comment("Regenerating file: $file");
                    file_put_contents($file, $this->generateFileContent($items));
                }
            }
        }
    }

    /**
     * @return array
     */
    private function findDefined() {
        $langPath = $this->getLanguagePath();

        if (!file_exists($langPath)) {
            return [];
        }

        $finder = new Finder();
        $files = $finder->in($langPath)->files()->getIterator();

        $groups = [];
        foreach ($files as $file) {
            $groupName = $file->getBasename('.' . $file->getExtension());
            if ($this->isExcluded($groupName)) {
                continue;
            }

            $groups[$groupName] = require $file->getRealPath();
        }

        return $groups;
    }

    /**
     * Get the path to the application's language files.
     *
     * @return string
     */
    private function getLanguagePath() {
        return base_path() . '/resources/lang/' . $this->locale;
    }

    /**
     * @param string $groupName
     * @return bool
     */
    private function isExcluded(string $groupName) {
        if ($this->excluded === null) {
            $excludeOption = $this->option('exclude-group');
            if (!$excludeOption) {
                $this->excluded = [];
            } else if ($excludeOption === '*') {
                $this->excluded = [];
            } else {
                $this->excluded = is_array($excludeOption) ? $excludeOption : [$excludeOption];
            }
        }

        return in_array($groupName, $this->excluded);
    }

    /**
     * @return array
     */
    public function findUsed() {
        $path = base_path();
        $groupKeys = [];
        $stringKeys = [];
        $functions = [
            'trans',
            'trans_choice',
            'Lang::get',
            'Lang::choice',
            'Lang::trans',
            'Lang::transChoice',
            '@lang',
            '@choice',
            '__',
            '$trans.get',
        ];

        $groupPattern =                          // See https://regex101.com/r/WEJqdL/6
            "[^\w|>]" .                          // Must not have an alphanum or _ or > before real method
            '(' . implode('|', $functions) . ')' .  // Must start with one of the functions
            "\(" .                               // Match opening parenthesis
            "[\'\"]" .                           // Match " or '
            '(' .                                // Start a new group to match:
            '[a-zA-Z0-9_-]+' .               // Must start with group
            "([.](?! )[^\1)]+)+" .             // Be followed by one or more items/keys
            ')' .                                // Close group
            "[\'\"]" .                           // Closing quote
            "[\),]";                            // Close parentheses or new parameter

        $stringPattern =
            "[^\w]" .                                     // Must not have an alphanum before real method
            '(' . implode('|', $functions) . ')' .             // Must start with one of the functions
            "\(" .                                          // Match opening parenthesis
            "(?P<quote>['\"])" .                            // Match " or ' and store in {quote}
            "(?P<string>(?:\\\k{quote}|(?!\k{quote}).)*)" . // Match any string that can be {quote} escaped
            "\k{quote}" .                                   // Match " or ' previously matched
            "[\),]";                                       // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage
        $finder = new Finder();
        $finder->in($path)->exclude('storage')->exclude('vendor')->name('*.php')->name('*.twig')->name('*.vue')->files();

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            // Search the current file for the pattern
            if (preg_match_all("/$groupPattern/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $key) {
                    $groupKeys[] = $key;
                }
            }

            if (preg_match_all("/$stringPattern/siU", $file->getContents(), $matches)) {
                foreach ($matches['string'] as $key) {
                    if (preg_match("/(^[a-zA-Z0-9_-]+([.][^\1) ]+)+$)/siU", $key, $groupMatches)) {
                        // group{.group}.key format, already in $groupKeys but also matched here
                        // do nothing, it has to be treated as a group
                        continue;
                    }

                    //skip keys which contain namespacing characters, unless they also contain a
                    //space, which makes it JSON.
                    if (!(Str::contains($key, '::') && Str::contains($key, '.'))
                        || Str::contains($key, ' ')) {
                        $stringKeys[] = $key;
                    }
                }
            }
        }
        // Remove duplicates
        $groupKeys = array_unique($groupKeys);
        $stringKeys = array_unique($stringKeys);

        $groups = [];
        // Add the translations to the database, if not existing.
        foreach ($groupKeys as $key) {
            // Split the group and item
            list($group, $item) = explode('.', $key, 2);
            if ($this->isExcluded($group)) {
                continue;
            }
            $groups[$group][$item] = '';
        }

        foreach ($stringKeys as $key) {
            $group = self::JSON_GROUP;
            $item = $key;
            if ($this->isExcluded($group)) {
                continue;
            }
            $groups[$group][$item] = '';
        }

        // Return the number of found translations
        return $groups;
    }

    /**
     * @param array $items
     * @return string
     */
    protected function generateFileContent($items): string {
        $arrayDef = var_export($items, true);
        $arrayDef = preg_replace('/^( +)?array \($/m', '\1[', $arrayDef);
        $arrayDef = preg_replace('/^( +)/m', '\1\1', $arrayDef);
        $arrayDef = preg_replace('/^( +)?\)(,)?$/m', '\1]\2', $arrayDef);
        return "<?php\n\nreturn " . $arrayDef . ";\n";
    }
}

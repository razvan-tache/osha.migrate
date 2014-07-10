<?php
/**
 * Created by PhpStorm.
 * User: razvan
 * Date: 7/2/14
 * Time: 4:56 PM
 */

class JSONNaceMigration extends DynamicMigration {

    protected $vocabulary = NULL;

    protected $cache = NULL;

    public function __construct($arguments) {
        parent::__construct();

        // TODO: Abstractization
        $this->description = 'Import the "Nace Codes" vocabulary data and hierarchy';
        $this->vocabulary = 'nace';
	
        $source_file = drupal_get_path('module', 'nace_migration'). '/' . 'nace2.original.json';

        $this->generateCacheFromFile($source_file);

        $this->map = new MigrateSQLMap($this->machineName,
            array(
                  'NACE_REV_2_CODE' => array(
                      'type' => 'varchar',
                      'length' => 30,
                      'not null' => true,
                      'description' => 'Nace code'
                  ),
            ),
            MigrateDestinationTerm::getKeySchema()
        );

        $this->source = new MigrateSourceJSON (
            $source_file, 'NACE_REV_2_CODE', $this->fields()
        );

        $term_options = MigrateDestinationTerm::options('en', 'text', TRUE);
        $this->destination = new MigrateDestinationTerm($this->vocabulary, $term_options);

        $this->addFieldMapping('field_nace_code', 'NACE_REV_2_CODE');
        $this->addFieldMapping('parent', 'parent');
        $this->addFieldMapping('format')
             ->defaultValue('plain_text');
        $this->addFieldMapping('name', 'NACE_REV_2_DESCRIPTION');
        $this->addFieldMapping('description_field:format')
             ->defaultValue('plain_text');
        $this->addFieldMapping('field_nace_changes', 'Change');

    }

    protected  function createStub($migration, array $source) {
        static $voc = NULL;
        if ($voc == NULL) {
            $voc = taxonomy_vocabulary_machine_name_load($this->vocabulary);
        }

        $term = new stdClass();
        $term->parent = 0;
        $term->language = 'en';
        $term->name = t('Stub for @code', array('@code' => $source[0]));
        $term->vid = $voc->vid;
        $term->field_nace_code[LANGUAGE_NONE][]['value'] = $source[0];

        taxonomy_term_save($term);
	
        return array($term->tid);
    }

    public function prepareRow($row) {
        $row->parent = 0;
        $row->language = 'en';

        $row->parent = $this->getParent($row);

        return TRUE;
    }

    function prepare($entity, stdClass $row){

        $correspondingRowAssocArray = $this->cache[$row->ID];
        $entity->language = 'en';
        $translationsData = array();
        $languages = $this->generateLanguagesWithFieldsMap();

        foreach($languages as $language => $fields) {
            $this->addTranslationToEntityFromRow($entity, $correspondingRowAssocArray, $language, $fields);
            $sourceLanguage = ($language == 'en' ? '' : 'en');
            $translationsData[$language] = array(
                'entity_type' => 'term',
                'entity_id'   => $entity->tid,
                'language'    => $language,
                'source'      => $sourceLanguage,
                'uid'         => '1',
                'status'      => '1',
                'translate'   => '0',
            );
        }

        $entity->translations = (object) array(
                'original' => 'en',
                'data' => $translationsData
        );



    }

    protected function generateCacheFromFile($source_file) {
        $rows = json_decode(file_get_contents($source_file), TRUE);
        foreach($rows as $row) {
            $this->cache[$row['ID']] = $row;
        }
    }

    protected function generateMachineName($class_name = NULL) {
        return 'TaxonomyNACECode';
    }

    private function getParent($row) {
        $parent_code = $this->getParentCode($row);
        if(!empty($parent_code)) {
            $parent_id = self::_getTidByCode($parent_code);
            if(empty($parent_id)) {
                $parent_id = $this->handleSourceMigration('TaxonomyNACECode', $parent_code);
            }
            return $parent_id;
        }
    }

    private static function _getTidByCode($code) {
        $query = new EntityFieldQuery();
        $result = $query
            ->entityCondition('entity_type', 'taxonomy_term')
            ->fieldCondition('field_nace_code', 'value', $code, '=')
            ->execute();
        if(!empty($result['taxonomy_term'])) {
            return current(array_keys($result['taxonomy_term']));
        }
        return array();
    }


    private function getParentCode($row) {
        $parent_code = NULL;
        $cached = $this->cache[$row->ID];
        if ($cached['Level'] > 3) {
            $parent_code = substr($cached['NACE_REV_2_CODE'], 0, -1);
        } else if ($cached['Level'] == 3) {
            $parent_code = substr($cached['NACE_REV_2_CODE'], 0, -2);
        } else if ($cached['Level'] == 2) {
            $parent_code = $cached['Sections for publication'];
        }

        return $parent_code;
    }

    function fields() {
        return array(
            'ID' => "id",
            'NACE_REV_2_CODE' => 'The corresponding NACE Code',
            'Sections for publication' => 'The main section the NACE Code',
            'Level' => 'The depth level of the respective tree',
            'Change' => 'The last changes made to the nace code',
            'parent' => 'The tid of the parent',
            'NACE_REV_2_DESCRIPTION' => 'The name of the NACE Code',
        );
    }

    private function generateLanguagesWithFieldsMap() {
        $languages = array(
            'en' => 'en', 'bg' => 'bg', 'cs' => 'cs', 'da' => 'da', 'el' => 'el',
            'et' => 'est', 'es' => 'es', 'fi' => 'fi', 'hr' => 'hr', 'hu' => 'hu',
            'fr' => 'fr', 'it' => 'it', 'lt' => 'lt', 'lv' => 'lv', 'mt' => 'mt',
            'nl' => 'nl', 'no' => 'no', 'pl' => 'pl', 'pt' => 'pt', 'ro' => 'ro',
            'ru' => 'ru', 'sv' => 'se', 'sl' => 'si', 'sk' => 'sk', 'tr' => 'tr',
            'de' => 'de'
        );

        $languagesWithDifferentFieldNameTemplates = array(
            'de', 'fr'
        );

        $defaultLanguage = 'en';

        $languageFieldsMap = array();
        foreach($languages as $language => $field_language_code) {
            if ($language == $defaultLanguage) {
                $languageFieldsMap[$language] = array(
                    'description' => 'NACE_REV_2_DESCRIPTION',
                    'excludes' => 'excludes',
                    'includes' => 'includes',
                    'includes_also' => 'includes also'
                );
            } else if (in_array($language, $languagesWithDifferentFieldNameTemplates)) {
                $languageFieldsMap[$language] = array(
                    'description' => $field_language_code . "_Description",
                    'excludes' => $field_language_code . "_Excludes",
                    'includes' => $field_language_code . "_Includes",
                    'includes_also' => $field_language_code . "_Includes also"
                );
            } else {
                $languageFieldsMap[$language] = array(
                    'description' => strtoupper($field_language_code) . "_DESC",
                    'excludes' => strtoupper($field_language_code) . "_EXCL",
                    'includes' => strtoupper($field_language_code) . "_INCL",
                    'includes_also' => strtoupper($field_language_code) . "_INCL_ALSO"
                );
            }
        }

        return $languageFieldsMap;
    }

    private function translateField(&$toBeTranslated, $translation) {
        if (!empty($translation)) {
            $toBeTranslated = $translation;
        }
    }
    private function addTranslationToEntityFromRow(&$entity, $row, $language, $fields) {
        $this->translateField($entity->name_field[$language][0]['value'], $row[$fields['description']]);
        $this->translateField($entity->description_field[$language][0]['value'], $row[$fields['description']]);
        $this->translateField($entity->field_nace_includes[$language][0]['value'], $row[$fields['includes']]);
        $this->translateField($entity->field_nace_includes_also[$language][0]['value'], $row[$fields['includes_also']]);
        $this->translateField($entity->field_nace_excludes[$language][0]['value'], $row[$fields['excludes']]);
    }
}


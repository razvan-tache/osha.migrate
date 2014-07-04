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

        $this->description = 'Import the "Nace Codes" vocabulary data and hierarchy';
        $this->vocabulary = 'nace_v2';

        $source_file = '/home/razvan/Work/Projects/drupal-training/json/nace2.original.json';
        $rows = json_decode(file_get_contents($source_file), TRUE);
        foreach($rows as $row) {
            $this->cache[$row['ID']] = $row;
        }

        $term_options = MigrateDestinationTerm::options('en', 'text', TRUE);

        $this->map = new MigrateSQLMap($this->machineName,
            array(
                'ID' => array(
                    'type' => 'float',
                    'length' => 9,
                    'not null' => true,
                    'unassigned' => true,
                    'description' => 'NACE Code'
                ),
            ),
            MigrateDestinationTerm::getKeySchema()
        );

        $this->source = new MigrateSourceJSON (
            $source_file, 'ID', $this->fields()
        );
//        $this->source = new MigrateSourceList (
//            new TaxonomyListJSON($source_file),
//            new TaxonomyTermJSON($source_file, array()),
//            $this->fields()
//        );

        $this->destination = new MigrateDestinationTerm($this->vocabulary, $term_options);
//        $this->addFieldMapping('tid', 'NACE_REV_2_CODE');
        $this->addFieldMapping('field_nace_code', 'NACE_REV_2_CODE');
        $this->addFieldMapping('name', 'NACE_REV_2_DESCRIPTION');
        #$this->addFieldMapping('description', 'NACE_REV_2_DESCRIPTION');
        #$this->addFieldMapping('field_nace_includes', 'includes');
//        $this->addFieldMapping('field_extra_includes', 'includes_also');
//        $this->addFieldMapping('field_excludes', 'excludes');
//        $this->addFieldMapping('field_changes', 'Change');
       $this->addFieldMapping('parent_name', 'parent_code');
       $this->addFieldMapping('parent', 'parent');
        #$this->addFieldMapping('format')->defaultValue('plain_text');

    }

    protected function generateMachineName($class_name = NULL) {
        return 'TaxonomyNACECode';
    }

    function fields() {
        return array(
//          'ID' => "id",
            'NACE_REV_2_CODE' => 'Nace Code',
//          'Section' => '',
//          'Level' => '',
            'NACE_REV_2_DESCRIPTION' => '',
            'includes' => '',
            'parent_code' => '',
            'parent' => '',
        );
    }

    protected  function createStub($migration, array $source) {
        static $voc = NULL;
        if ($voc == NULL) {
            $voc = taxonomy_vocabulary_machine_name_load($this->vocabulary);
        }

        $term = new stdClass();
        $term->parent = 0;
        $term->language = 'en';
        $term->name = t('Stub for @code', array('@code' => $source));
        $term->vid = $voc->vid;
        $term->field_nace_code[LANGUAGE_NONE][] = $source;

        return $term->tid;
    }

    public function prepareRow($row) {
        $row->parent = 0;
        $row->language = 'en';
        if(strlen($row->NACE_REV_2_CODE) != 1) {
            $parent_code = $this->getParentCode($row);
            $parent_id = self::_getTidByCode($parent_code);
            if(empty($parent_id)) {
                $parent_id = $this->handleSourceMigration('TaxonomyNACECode', $parent_code);
                $row->parent = $parent_id;
            } else {
                $row->parent = $parent_id;
            }
            $row->parent_code = $parent_code;
        }
        return TRUE;
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

        $cached = $this->cache[$row->ID];
        if ($cached['Level'] > 3) {
            $row->parent_code = substr($cached['NACE_REV_2_CODE'], 0, -1);
        } else if ($cached['Level'] == 3) {
            $row->parent_code = substr($cached['NACE_REV_2_CODE'], 0, -2);
        } else if ($cached['Level'] == 2) {
            $row->parent_code = $cached['Sections for publication'];
        }
        return $row->parent_code;
    }
}

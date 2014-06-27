<?php
 
class JSONNewsMigration extends Migration {
  /**
   * A constructor 
   */
  public function __construct() {
    parent::__construct(MigrateGroup::getInstance('json_migration'));
    // source json file
    $list_url = 'json/news.json';

    // no options, or you could specify some
    $http_options = array();
    // map for the migration
    $this->map = new MigrateSQLMap($this->machineName,
        array(
          'id' => array(
            'type' => 'int',
            'not null' => true,
	    'unassigned' => true,
          ),
        ),
        MigrateDestinationNode::getKeySchema()
    );

    $this->source = new MigrateSourceJSON( $list_url, 'id', $this->fields() );

    // destination node type is json (because this is an example)
    $this->destination = new MigrateDestinationNode('news');

    // map node's title field to the title in the json content
    $this->addFieldMapping('title', 'title');
    // map node's body field to the content in the json content
    $this->addFieldMapping('body', 'text');
    // map node's body summary field to the title in the json content
    $this->addFieldMapping('body:summary', 'description');

    // map node's tags with create term if not exist
    $this->addFieldMapping('field_news_tags', 'subject')
	 ->separator(',');
    $this->addFieldMapping('field_news_tags:create_term')
	 ->defaultValue(TRUE);

    // example image migration
    $this->addFieldMapping('field_news_image', 'image_link');
    $this->addFieldMapping('field_news_image:alt')
	 ->defaultValue('Something');
    $this->addFieldMapping('field_news_image:title')
         ->defaultValue('Title custom');
    
    //apparently you can do stuff like this:
    $this->addFieldMapping('comment', null)->defaultValue(COMMENT_NODE_CLOSED);
  }
  
  /**
   * Return the fields (this is cleaner than passing in the array in the MigrateSourceList class above)
   * @return array
   */
  function fields() {
    return array(
        "id" => "unique ID for each source row",
        "description" => "the summary of each news node",
	"text" => "the content of each news node",
        "title" => "the title of the news",
	"subject" => "news tags",
	"image_link" => "the link to the image"
    );
  }
  
}

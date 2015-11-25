<?php
namespace Rodriguez\Flex;

use Illuminate\Support\Facades\Config;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use Carbon\Carbon;

/**
 * 
 */
trait FlexTrait 
{
    /**
     * @var null|float
     */
    protected $document_score = null;

    /**
     * @var null|float
     */
    protected $document_version = null;

    /**
     * @var bool
     */
    protected $is_document = false;

    /**
     * @var array
     */
    protected $highlighted = array();

    /**
     *  @var int
     */
    protected $result_size = 1000;

    /**
     * Returns match count
     *
     * @param array $body
     * @return integer
     */
    public static function count(Array $body)
    {
        $instance       = new static;
        $params         = $instance->basicElasticParams();
        $params['body'] = $body;

        $response = $instance->getElasticClient()->count($params);

        return intval($response['count']);
    }

    /**
     * Builds an arbitrary query.
     *
     * @param array $body
     * @return ElasticCollection
     */
    public static function search(Array $body)
    {
        $instance       = new static;
        $params         = $instance->basicElasticParams();
        $params['body'] = $body;

        $response = $instance->getElasticClient()->search($params);

        return new ElasticCollection($response, $instance);
    }

    /**
     * Builds a match query.
     *
     * @param string $title
     * @param string $query
     * @return ElasticCollection
     */
    public static function match($title, $query)
    {
        $body = [
            'query' => [
                'match' => [
                    $title => $query
                ]
            ],
            'size' => $this->result_size
        ];

        return static::search($body);
    }

    /**
     * Builds a multi_match query.
     *
     * @param array $fields
     * @param string $query
     * @return ElasticCollection
     */
    public static function multiMatch(Array $fields, $query)
    {
        $body = [
            'query' => [
                'multi_match' => [
                    'query'     => $query,
                    'fields'    => $fields
                ]
            ],
            'size' => $this->result_size
        ];

        return static::search($body);
    }

    /**
     * Builds a fuzzy query.
     *
     * @param string $field
     * @param string $value
     * @param string $fuzziness
     * @return ElasticCollection
     */
    public static function fuzzy($field, $value, $fuzziness = 'AUTO')
    {
        $body = [
            'query' => [
                'fuzzy' => [
                    $field => [
                        'value'     => $value,
                        'fuzziness' => $fuzziness
                    ]
                ]
            ],
            'size' => $this->result_size
        ];

        return static::search($body);
    }

    /**
     * Builds a geoshape query.
     *
     * @param string $field
     * @param array $coordinates
     * @param string $type
     * @return ElasticCollection
     */
    public static function geoshape($field, Array $coordinates, $type = 'envelope')
    {
        $body = [
            'query' => [
                'geo_shape' => [
                    $field => [
                        'shape' => [
                            'type'          => $type,
                            'coordinates'   => $coordinates
                        ]
                    ]
                ]
            ],
            'size' => $this->result_size
        ];

        return static::search($body);
    }

    /**
     * Builds an ids query.
     *
     * @param array $values
     * @return ElasticCollection
     */
    public static function ids(Array $values)
    {
        $body = [
            'query' => [
                'ids' => [
                    'values' => $values
                ]
            ],
            'size' => $this->result_size
        ];

        return static::search($body);
    }

    /**
     * Builds a more_like_this query.
     *
     * @param array $fields
     * @param array $ids
     * @param int $minTermFreq
     * @param float $percentTermsToMatch
     * @param int $minWordLength
     * @return ElasticCollection
     */
    public static function moreLikeThis(Array $fields, Array $ids, $min_term_freq = 1, $percent_terms_to_match = 0.5, $min_word_length = 3)
    {
        $body = [
            'query' => [
                'more_like_this' => [
                    'fields'                    => $fields,
                    'ids'                       => $ids,
                    'min_term_freq'             => $min_term_freq,
                    'percent_terms_to_match'    => $percent_terms_to_match,
                    'min_word_length'           => $min_word_length,
                ]
            ],
            'size' => $this->result_size
        ];

        return static::search($body);
    }

    /**
     * Gets mappings.
     *
     * @return array
     */
    public static function getMapping()
    {
        $instance   = new static;
        $params     = $instance->basicElasticParams();

        return $instance->getElasticClient()->indices()->getMapping($params);
    }

    /**
     * Puts mappings.
     *
     * @return array
     */
    public static function putMapping()
    {
        $instance   = new static;
        $mapping    = $instance->basicElasticParams();
        $params     = [
            '_source'       => array('enabled' => true),
            'properties'    => $instance->getMappingProperties()
        ];
        
        $mapping['body'][$instance->getTypeName()] = $params;

        return $instance->getElasticClient()->indices()->putMapping($mapping);
    }

    /**
     * Deletes mappings.
     *
     * @return array
     */
    public static function deleteMapping()
    {
        $instance   = new static;
        $params     = $instance->basicElasticParams();

        return $instance->getElasticClient()->indices()->deleteMapping($params);
    }

    /**
     * Checks if mappings exist.
     *
     * @return bool
     */
    public static function hasMapping()
    {
        $instance   = new static;
        $mapping    = $instance->getMapping();

        return (empty($mapping)) ? false : true;
    }

    /**
     * Rebuilds mappings.
     *
     * @return array
     */
    public static function rebuildMapping()
    {
        $instance = new static;

        if ($instance->hasMapping()) {
            $instance->deleteMapping();
        }

        return $instance->putMapping();
    }

    /**
     * Return the current result_size value.
     * 
     * @return int  The current value of result_size.
     */
    public function resultSize()
    {
        return $this->result_size;
    }

    /**
     * Set the result_size value.
     * 
     * @param int $result_size  The max number of 
     * search results returned by elasticsearch.
     */
    public function setResultSize($result_size)
    {
        $this->result_size = $result_size;
    }

    /**
     * Gets mapping properties from the model.
     *
     * @return array
     */
    protected function getMappingProperties()
    {
        return $this->mappingProperties;
    }

    /**
     * Gets the model's fields.
     *
     * @return array
     */
    public function documentFields()
    {
        return $this->toArray();
    }

    /**
     * Indexes the model in Elasticsearch.
     *
     * @return array
     */
    public function index()
    {
        $params         = $this->basicElasticParams(true);
        $params['body'] = $this->documentFields();

        return $this->getElasticClient()->index($params);
    }

    /**
     * Updates the model's index.
     *
     * @param array $fields
     * @return array|bool
     */
    public function updateIndex(Array $fields = array())
    {
        // Use the specified fields for the update.
        if ($fields) {
            $body = $fields;
        }

        // Or get the model's modified fields.
        elseif ($this->isDirty()) {
            $body = $this->getDirty();
        } else {
            return true;
        }

        foreach ($body as $field => $value) {
            if ($value instanceof Carbon) {
                $body[$field] = $value->toDateTimeString();
            }
        }
        
        $params                 = $this->basicElasticParams(true);
        $params['body']['doc']  = $body;

        try {
            return $this->getElasticClient()->update($params);
        } catch (Missing404Exception $e) {
            return false;
        }
    }

    /**
     * Removes the model's index.
     *
     * @return array|bool
     */
    public function removeIndex()
    {
        try {
            return $this->getElasticClient()->delete($this->basicElasticParams(true));
        } catch (Missing404Exception $e) {
            return false;
        }
    }

    /**
     * Reindexes the model.
     *
     * @return array
     */
    public function reindex()
    {
        $this->removeIndex();

        return $this->index();
    }

    /**
     * @param int $version
     * @return array|bool
     */
    public function indexWithVersion($version)
    {
        try {
            $params             = $this->basicElasticParams(true);
            $params['body']     = $this->documentFields();
            $params['version']  = $version;

            return $this->getElasticClient()->index($params);
        } catch (Missing404Exception $e) {
            return false;
        } catch (Conflict409Exception $e) {
            return false;
        }
    }

    /**
     * Runs indexing functions before calling Eloquent's save() method.
     *
     * @param array $options
     * @return mixed
     */
    public function save(Array $options = array())
    {
        if (Config::get('flex::config.auto_index')) {
            $params = $this->basicElasticParams(true);

            // When creating a model, Eloquent still
            // uses the save() method. In this case,
            // the field still doesn't have an id, so
            // it is saved first, and then indexed.
            if ( ! isset($params['id'])) {
                $saved = parent::save($options);
                $this->index();

                return $saved;
            }

            // Did the update fail? If so, the index
            // doesn't exist. Let's create it.
            if ( ! $this->updateIndex()) {
                $this->index();
            }
        }

        return parent::save($options);
    }

    /**
     * Deletes the index before calling Eloquent's delete method.
     *
     * @return mixed
     */
    public function delete()
    {
        if (Config::get('flex::config.auto_index')) {
            $this->removeIndex();
        }

        return parent::delete();
    }

    /**
     * Returns the index name. If the index name is not set
     * in the model, then get it from the config file.
     *
     * @return string
     */
    public function getIndexName()
    {
        if (isset($this->index_name)) {
            return $this->index_name;
        }

        return Config::get('flex::config.index');
    }

    /**
     * Returns the type name. If the type name is not 
     * set in the model, then use the table name.
     *
     * @return string
     */
    public function getTypeName()
    {
        if (isset($this->type_name)) {
            return $this->type_name;
        }

        return $this->getTable();
    }

    /**
     * Returns wheather or not the model represents 
     * an Elasticsearch document.
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->is_document;
    }

    /**
     * Returns the document score.
     *
     * @return null\float
     */
    public function documentScore()
    {
        return $this->document_score;
    }

    /**
     * Returns the document version.
     *
     * @return null|int
     */
    public function documentVersion()
    {
        return $this->document_version;
    }

    /**
     * Returns a highlighted field.
     *
     * @param string $field
     * @return mixed
     */
    public function highlight($field)
    {
        if (isset($this->highlighted[$field])) {
            return $this->highlighted[$field];
        }

        return false;
    }

    /**
     * Instructs Eloquent to use a custom collection class.
     *
     * @param array $models
     * @return FlexCollection
     */
    public function newCollection(array $models = array())
    {
        return new FlexCollection($models);
    }

    /**
     * Fills a model's attributes with Elasticsearch result data.
     *
     * @param array $hit
     * @return mixed
     */
    public function newFromElasticResults(Array $hit)
    {
        $instance               = $this->newInstance(array(), true);
        $attributes             = $hit['_source'];
        $instance->is_document   = true;

        if (isset($hit['_score'])) {
            $instance->document_score = $hit['_score'];
        }

        if (isset($hit['_version'])) {
            $instance->document_version = $hit['_version'];
        }

        if (isset($hit['highlight'])) {
            foreach ($hit['highlight'] as $field => $value) {
                $instance->highlighted[$field] = $value[0];
            }
        }

        $instance->setRawAttributes($attributes, true);

        return $instance;
    }

    /**
     * Sets the basic Elasticsearch parameters.
     *
     * @param bool $withId
     * @return array
     */
    protected function basicElasticParams($withId = false)
    {
        $params = array(
            'index' => $this->getIndexName(),
            'type'  => $this->getTypeName()
        );

        if ($withId and $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        return $params;
    }

    /**
     * Returns an Elasticsearch\Client instance.
     *
     * @return ElasticSearch
     */
    protected function getElasticClient()
    {   
        // Create an Elasticsearch client instance with config params and
        // bind it to the IoC container so we can easily create objects.
        return ClientBuilder::fromConfig(Config::get('flex::elasticsearch'));
    }
}
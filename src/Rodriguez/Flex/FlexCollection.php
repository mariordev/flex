<?php
namespace Rodriguez\Flex;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Elasticsearch\ClientBuilder;

/**
 * 
 */
class FlexCollection extends Collection 
{   
    /**
     * Indexes all the results from the collection.
     *
     * @return array
     */
    public function index()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $params = array();

        foreach ($this->all() as $item) {
            $params['body'][] = [
                'index' => [
                    '_index'    => $item->getIndexName(),
                    '_type'     => $item->getTypeName(),
                    '_id'       => $item->getKey()
                ]
            ];
            $params['body'][] = $item->documentFields();
        }

        return $this->getElasticClient()->bulk($params);
    }

    /**
     * Deletes the indexes of the collection.
     *
     * @return array
     */
    public function removeIndex()
    {
        if ($this->isEmpty()) {
            return false;
        }
        $params = array();
        foreach ($this->all() as $item) {
            $params['body'][] = [
                'delete' => [
                    '_index'    => $item->getIndexName(),
                    '_type'     => $item->getTypeName(),
                    '_id'       => $item->getKey()
                ]
            ];
        }
        return $this->getElasticClient()->bulk($params);
    }

    /**
     * Reindexes all the results from the collection.
     *
     * @return array
     */
    public function reindex()
    {
        $this->removeIndex();
        return $this->index();
    }

    /**
     * Create and return an Elasticsearch\Client instance.
     *
     * @return Elasticsearch\Client
     */
    protected function getElasticClient()
    {   
        // Create an Elasticsearch client instance with given params and
        // bind it to the IoC container so we can easily create objects.
        return ClientBuilder::fromConfig(Config::get('flex::elasticsearch'));
    }
}
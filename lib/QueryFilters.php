<?php

namespace CptTables\Lib;

class QueryFilters
{
    public function __construct(Db $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;

        add_filter('query', [$this, 'updateQueryTables']);
    }

    public function updateQueryTables(string $query)
    {
        $table = $this->determineTable($query);

        if ($table && in_array($table, $this->config['post_types'])) {
            $query = str_replace($this->config['default_post_table'], $table, $query);
            $query = str_replace($this->config['default_meta_table'], $table . '_meta', $query);
        }

        return $query;
    }

    private function determineTable(string $query)
    {
        if ($table = $this->getPostTypeFromQuery($query)) {
            return $table;
        }

        if ($table = $this->lookupPostTypeInDatabase($query)) {
            return $table;
        }
    }

    /**
     * Grabs the post id from the query and looks up the post type for this id
     * in the wp_posts table
     *
     * @param  string $query
     * @return string|null
     */
    public function lookupPostTypeInDatabase(string $query)
    {
        if ($ids = $this->getPostIdsFromQuery($query)) {
            return $this->getPostTypeById($ids);
        }
    }

    /**
     * Tries to parse the post type from the query
     *
     * @param  string $query
     * @return bool|string
     */
    private function getPostTypeFromQuery(string $query)
    {
        preg_match("/`?post_type`?\s*=\s*'([a-zA-Z]*)'/", $query, $postType);

        if ($postType = array_pop($postType)) {
            return $postType;
        }
    }

    /**
     * Tries to parse the post id(s) from the query
     *
     * @param  string $query
     * @return bool|string
     */
    private function getPostIdsFromQuery(string $query)
    {
        preg_match(
            sprintf(
                "/(?:SELECT.*FROM\s(?:%s|%s)\s*WHERE.*(?:ID|post_id)+\s*IN\s\(+\s*'?([\d\s,]*)'?\)|SELECT.*FROM\s(?:%s|%s)\s*WHERE.*(?:ID|post_id)+\s*=+\s*'?(\d*)'?)/",
                $this->config['default_post_table'],
                $this->config['default_meta_table'],
                $this->config['default_post_table'],
                $this->config['default_meta_table']
            ),
            $query,
            $ids
        );

        return array_pop($ids);
    }

    /**
     * Looks up post in wp_posts
     *
     * @param  string $ids
     * @return string
     */
    public function getPostTypeById($ids)
    {
        $key = __METHOD__ . $ids;

        if (!$cached = wp_cache_get($key)) {
            $ids = explode(',', $ids);

            $cached = $this->db->value(
                sprintf(
                    "SELECT post_type, ID as identifier FROM %s HAVING identifier IN (%s) LIMIT 1",
                    $this->config['default_post_table'],
                    implode(',', array_fill(0, count($ids), '?'))
                ),
                ...$ids
            );

            wp_cache_set($key, $cached);
        }

        return $cached;
    }
}
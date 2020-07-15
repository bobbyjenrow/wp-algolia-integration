<?php

if (!(defined('WP_CLI') && WP_CLI)) {
    return;
}

class Algolia_Post_Iterator implements Iterator
{
    /**
     * @var array
     */
    private $queryArgs;

    private $key;

    private $paged;

    private $posts;
    private $type;

    public function __construct($type, array $queryArgs = [])
    {
        $this->type = $type;
        $this->queryArgs = ['post_type' => $type] + $queryArgs;
    }

    public function current()
    {
        return $this->serialize($this->posts[$this->key]);
    }

    public function next()
    {
        $this->key++;
    }

    public function key()
    {
        $this->key;
    }

    public function valid()
    {
        if (isset($this->posts[$this->key])) {
            return true;
        }

        $this->paged++;
        $query = new WP_Query(['paged' => $this->paged] + $this->queryArgs);

        if (!$query->have_posts()) {
            return false;
        }

        $this->posts = $query->posts;
        $this->key = 0;

        return true;
    }

    public function rewind()
    {
        $this->key = 0;
        $this->paged = 0;
        $this->posts = [];
    }

    private function serialize(WP_Post $post)
    {
        $record = (array) apply_filters($this->type . '_to_record', $post);

        if (!isset($record['objectID'])) {
            $record['objectID'] = implode('#', [$post->post_type, $post->ID]);
        }

        return $record;
    }
}
class Algolia_Command
{
    public function hello($args, $assoc_args)
    {
        WP_CLI::success('Algolia is correctly loaded 🎉');
    }

    public function copy_config($args, $assoc_args)
    {
        global $algolia;

        $srcIndexName = $assoc_args['from'];
        $destIndexName = $assoc_args['to'];

        if (!$srcIndexName || !$destIndexName) {
            throw new InvalidArgumentException('--from and --to arguments are required');
        }

        $scope = [];
        if (isset($assoc_args['settings']) && $assoc_args['settings']) {
            $scope[] = 'settings';
        }
        if (isset($assoc_args['synonyms']) && $assoc_args['synonyms']) {
            $scope[] = 'synonyms';
        }
        if (isset($assoc_args['rules']) && $assoc_args['rules']) {
            $scope[] = 'rules';
        }

        if (!empty($scope)) {
            $algolia->copyIndex($srcIndexName, $destIndexName, ['scope' => $scope]);
            WP_CLI::success('Copied ' . implode(', ', $scope) . " from $srcIndexName to $destIndexName");
        } else {
            WP_CLI::warning('Nothing to copy, use --settings, --synonyms or --rules.');
        }
    }
    public function set_config($args, $assoc_args)
    {
        global $algolia;

        $canonicalIndexName = $assoc_args['index'];
        if (!$canonicalIndexName) {
            throw new InvalidArgumentException('--index argument is required');
        }

        $index = $algolia->initIndex(
            apply_filters('algolia_index_name', $canonicalIndexName)
        );

        if ($assoc_args['settings']) {
            $settings = (array) apply_filters('get_' . $canonicalIndexName . '_settings', []);
            if ($settings) {
                $index->setSettings($settings);
                WP_CLI::success('Push settings to ' . $index->getIndexName());
            }
        }

        if ($assoc_args['synonyms']) {
            $synonyms = (array) apply_filters('get_' . $canonicalIndexName . '_synonyms', []);
            if ($synonyms) {
                $index->replaceAllSynonyms($synonyms);
                WP_CLI::success('Push synonyms to ' . $index->getIndexName());
            }
        }

        if ($assoc_args['rules']) {
            $rules = (array) apply_filters('get_' . $canonicalIndexName . '$rules', []);
            if ($rules) {
                $index->replaceAllRules($rules);
                WP_CLI::success('Push query rules to ' . $index->getIndexName());
            }
        }
    }
    public function reindex_posts_atomic($args, $assoc_args)
    {
        global $algolia;

        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'post';

        $index = $algolia->initIndex(
            apply_filters('algolia_index_name', $type)
        );

        $queryArgs = [
            'posts_per_page' => 100,
            'post_status' => 'publish',
        ];

        $iterator = new Algolia_Post_Iterator($type, $queryArgs);

        $index->replaceAllObjects($iterator);

        WP_CLI::success("Reindexed $type posts in Algolia");
    }

    public function reindex_posts($args, $assoc_args)
    {
        global $algolia;
        global $table_prefix;

        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'post';

        $indexName = $table_prefix . $type;
        $index = $algolia->initIndex(
            apply_filters('algolia_index_name', $indexName, $type)
        );
        $index->clearObjects()->wait();


        $paged = 1;
        $count = 0;
        do {
            $posts = new WP_Query([
                'posts_per_page' => 100,
                'paged' => $paged,
                'post_type' => $type,
                'post_status' => 'publish',
            ]);

            if (!$posts->have_posts()) {
                break;
            }

            $records = [];
            foreach ($posts->posts as $post) {
                if ($assoc_args['verbose']) {
                    WP_CLI::line('Indexing [' . $post->post_title . ']');
                }
                $record = (array) apply_filters($type . '_to_record', $post);

                if (!isset($record['objectID'])) {
                    $record['objectID'] = implode('#', [$post->post_type, $post->ID]);
                }

                $records[] = $record;
                $count++;
            }

            $index->saveObjects($records);

            $paged++;
        } while (true);

        WP_CLI::success("$count $type entries indexed in Algolia");
    }

}


WP_CLI::add_command('algolia', 'Algolia_Command');

/* DOCS
// PHP API
https: //www.algolia.com/doc/api-client/getting-started/install/php/?language=php

// ACF
https: //discourse.algolia.com/t/problem-with-custom-searchable-attributes/3598
https: //community.algolia.com/wordpress/advanced-custom-fields.html
https: //discourse.algolia.com/t/advanced-custom-fields-integration-in-wordpress/2205

// UI
https: //www.algolia.com/doc/api-reference/widgets/refinement-list/react/
https: //www.algolia.com/doc/api-reference/api-parameters/attributesForFaceting/
https://www.algolia.com/apps/N9KONKCZY2/explorer/display/wp_algoliasearchable_posts
*/
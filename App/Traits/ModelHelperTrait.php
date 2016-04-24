<?php

namespace App\Submodules\ToolsLaravelMicroservice\App\Traits;

trait ModelHelperTrait
{
    /**
     * Results from the magicSearch method.
     *
     * @var Illuminate\Pagination\LengthAwarePaginator
     */
    protected $magicResult;

    /**
     * Searches for a model based on any number of input received.
     * Searchable columns/attributes are specified in the model's
     * $queryable property.
     *
     * @param  array $input
     * @param  int $pagination
     * @return Illuminate\Pagination\LengthAwarePaginator
     *
     * @throws Psy\Exception\FatalErrorException;
     * @throws Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function magicSearch(array $input, $pagination = 5)
    {
        if (is_null($this->queryable)) {
            throw new \FatalErrorException(
                'Please specify columns available '.
                'for Magic Search in $queryable.'
            );
        }

        // Find any input that matches $queryable
        $searchKeys = array_intersect(
            array_keys($input),
            $this->queryable
        );

        if (count($searchKeys) < 1) {
            throw new \BadRequestHttpException(
                'No input keys matched any searchable columns/fields.'
            );
        }

        $model = $this->query();

        foreach ($searchKeys as $key) {
            if (is_string($input[$key])) {
                $model->where($key, $input[$key]);
            } elseif (is_array($input[$key])) {
                $model->whereIn($key, $input[$key]);
            }
        }

        $result = $model->paginate($pagination);

        $this->magicResult = $result;

        return $result;
    }

    /**
     * Returns any models found with the magicSearch method as an array.
     *
     * @return array
     */
    public function extract()
    {
        if (!is_null($this->magicResult)) {
            return $this->magicResult->toArray()['data'];
        }
        return collect([$this->toArray()]);
    }

    /**
     * Replaces the 'data' key name returned by LengthAwarePaginator
     * with a model-centric term, e.g., 'users'
     *
     * @param  string $override
     * @return array
     */
    public function dataKeyToModelName($override = null)
    {
        if (!isset($this->magicResult)) {
            return false;
        }

        $result = $this->magicResult->toArray();

        $this->trimPagination($result);

        if (!isset($result['data'])) {
            return false;
        }

        if (is_null($override)) {
            $name = $this->table;
        } else {
            $name = $override;
        }

        $result[$name] = $result['data'];
        unset($result['data']);

        return $result;
    }

    /**
     * Trims excess pagination data if there is only one page.
     *
     * @param  $result array
     * @return void
     */
    protected function trimPagination(&$result)
    {
        $result = array_only($result, ['total', 'data']);
    }

    /**
     * Returns any models found from the magicSearch method
     *
     * @return Illuminate\Pagination\LengthAwarePaginator
     */
    public function getResult()
    {
        if (!isset($this->magicResult)) {
            return false;
        }

        return $this->magicResult;
    }

    /**
     * Counts the number of models we expected to find and
     * throws an exception if it is not correct.
     *
     * @param  int $n
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function expected(int $n)
    {
        // Number of models expected to be found.
        // Throw an exception otherwise
        $count = count($this->extract());
        if ($count != $n) {
            throw new \ModelNotFoundException(
                'Incorrect number of models found in collection. '.
                "Expected: $n | Found: $count"
            );
        }
    }
}
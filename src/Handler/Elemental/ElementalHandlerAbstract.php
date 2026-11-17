<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Elemental\ElementalAreaListener;
use SilverStripe\Snapshots\Handler\HandlerAbstract;

/**
 * Parent class for elemental inline editor event hooks
 *
 * Reads the record IDs from the event context of either source
 */
abstract class ElementalHandlerAbstract extends HandlerAbstract
{
    protected function getBlockID(EventContextInterface $context): int
    {
        return $this->getContextID($context, 'id', 'blockId');
    }

    protected function getElementalAreaID(EventContextInterface $context): int
    {
        return $this->getContextID($context, 'elementalAreaID', 'elementalAreaID');
    }

    protected function getContextID(EventContextInterface $context, string $restKey, string $graphQLKey): int
    {
        $data = $context->get('data');

        if (is_array($data) && array_key_exists($restKey, $data)) {
            return $this->normaliseID($data[$restKey]);
        }

        $params = $this->getGraphQLParams($context);

        if (!array_key_exists($graphQLKey, $params)) {
            return 0;
        }

        return $this->normaliseID($params[$graphQLKey]);
    }

    private function getGraphQLParams(EventContextInterface $context): array
    {
        // GraphQL 4
        $variables = $context->get('variables');

        if (is_array($variables)) {
            return $variables;
        }

        // GraphQL 3
        $params = $context->get('params');

        if (is_array($params)) {
            return $params;
        }

        return [];
    }

    private function normaliseID(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return 0;
        }

        $id = (int) $value;

        if ($id < 1) {
            return 0;
        }

        return $id;
    }
}

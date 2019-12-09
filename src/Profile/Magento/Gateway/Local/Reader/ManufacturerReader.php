<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ManufacturerReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT_MANUFACTURER;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $fetchedManufacturers = $this->fetchManufacturers();
        $ids = array_column($fetchedManufacturers, 'option_id');
        $fetchedTranslations = $this->fetchTranslations($ids);

        foreach ($fetchedManufacturers as &$manufacturer) {
            $optionId = $manufacturer['option_id'];

            if (isset($fetchedTranslations[$optionId])) {
                foreach ($fetchedTranslations[$optionId] as $translation) {
                    $store_id = $translation['store_id'];
                    $attribute_id = $translation['attribute_id'];
                    $value = $translation['value'];

                    $manufacturer['translations'][$store_id]['name']['value'] = $value;
                    $manufacturer['translations'][$store_id]['name']['attribute_id'] = $attribute_id;
                }
            }
        }

        return $fetchedManufacturers;
    }

    private function fetchManufacturers(): array
    {
        $sql = <<<SQL
SELECT
    optionValue.option_id,
    optionValue.value
FROM {$this->tablePrefix}eav_attribute_option_value optionValue
INNER JOIN {$this->tablePrefix}eav_attribute_option AS attributeOption ON optionValue.option_id = attributeOption.option_id
INNER JOIN {$this->tablePrefix}eav_attribute AS attribute ON attribute.attribute_id = attributeOption.attribute_id AND attribute.attribute_code = "manufacturer";
WHERE optionValue.store_id = 0
SQL;

        return $this->connection->executeQuery($sql)->fetchAll();
    }

    private function fetchTranslations(array $ids): array
    {
        $sql = <<<SQL
SELECT
    optionValue.option_id,
    optionValue.store_id,
    attributeOption.attribute_id,
    optionValue.value
FROM {$this->tablePrefix}eav_attribute_option_value optionValue
INNER JOIN {$this->tablePrefix}eav_attribute_option AS attributeOption ON optionValue.option_id = attributeOption.option_id
INNER JOIN {$this->tablePrefix}eav_attribute AS attribute ON attribute.attribute_id = attributeOption.attribute_id AND attribute.attribute_code = "manufacturer"
WHERE optionValue.store_id != 0 AND optionValue.option_id IN (?);
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_INT_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}

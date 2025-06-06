<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\PrestaShop\Core\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Builds search & count queries for address grid.
 */
final class AddressQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /**
     * @var DoctrineSearchCriteriaApplicatorInterface
     */
    private $searchCriteriaApplicator;

    /**
     * @var int
     */
    private $contextLangId;

    /**
     * @var int[]
     */
    private $contextShopIds;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     * @param DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator
     * @param int $contextLangId
     * @param array $contextShopIds
     */
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator,
        int $contextLangId,
        array $contextShopIds
    ) {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
        $this->contextLangId = $contextLangId;
        $this->contextShopIds = $contextShopIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getQueryBuilder($searchCriteria->getFilters());

        $qb
            ->select('a.`id_address`, a.`firstname`, a.`lastname`, a.`address1`, a.`postcode`, a.`city`')
            ->addSelect('cl.`name` as country_name')
        ;

        $this->searchCriteriaApplicator
            ->applyPagination($searchCriteria, $qb)
            ->applySorting($searchCriteria, $qb)
        ;

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getQueryBuilder($searchCriteria->getFilters())
            ->select('COUNT(DISTINCT a.`id_address`)')
        ;

        return $qb;
    }

    /**
     * Gets query builder with the common sql used for displaying addresses list and applying filter actions.
     *
     * @param array $filters
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection
            ->createQueryBuilder()
            ->from($this->dbPrefix . 'address', 'a')
            ->where('a.`id_customer` != 0')
            ->andWhere('a.`deleted` = 0');

        $qb->leftJoin(
            'a',
            $this->dbPrefix . 'country',
            'c',
            'a.`id_country` = c.`id_country`'
        );

        $qb->leftJoin(
            'c',
            $this->dbPrefix . 'country_lang',
            'cl',
            'c.`id_country` = cl.`id_country` AND cl.`id_lang` = :idLang'
        );

        $qb->leftJoin(
            'a',
            $this->dbPrefix . 'customer',
            'customer',
            'a.`id_customer` = customer.`id_customer`'
        );
		
		// Aplicar el filtro de clientes asignados si el empleado no es administrador
    $id_employee = $this->getEmployeeIdFromSession(); // Asegúrate de tener este método implementado
    $employee = new \Employee($id_employee);
    $id_profile = $employee->id_profile;

    if ($id_profile != _PS_ADMIN_PROFILE_) {
        $qb->andWhere('a.id_customer IN (
            SELECT id_cliente FROM ' . $this->dbPrefix . 'comerciales_clientes WHERE id_comercial = :id_employee
        )');
        $qb->setParameter('id_employee', $id_employee);
    }

        $qb->andWhere('customer.id_shop IN (:context_shop_ids)')
            ->setParameter('context_shop_ids', $this->contextShopIds, Connection::PARAM_INT_ARRAY)
            ->setParameter('idLang', $this->contextLangId);

        $this->applyFilters($qb, $filters);

        return $qb;
    }

    /**
     * Apply filters to address query builder.
     *
     * @param array $filters
     * @param QueryBuilder $qb
     */
    private function applyFilters(QueryBuilder $qb, array $filters)
    {
        $allowedFiltersMap = [
            'id_address' => 'a.id_address',
            'firstname' => 'a.firstname',
            'lastname' => 'a.lastname',
            'address1' => 'a.address1',
            'postcode' => 'a.postcode',
            'city' => 'a.city',
            'id_country' => 'cl.id_country',
        ];

        foreach ($filters as $filterName => $value) {
            if (!array_key_exists($filterName, $allowedFiltersMap) || empty($value)) {
                continue;
            }

            if ('id_country' === $filterName) {
                $qb->andWhere($allowedFiltersMap[$filterName] . ' = :' . $filterName)
                    ->setParameter($filterName, $value);

                continue;
            }

            $qb->andWhere($allowedFiltersMap[$filterName] . ' LIKE :' . $filterName)
                ->setParameter($filterName, '%' . $value . '%');
        }
    }
	
	private function getEmployeeIdFromSession()
{
    return \Context::getContext()->employee->id;
}
}

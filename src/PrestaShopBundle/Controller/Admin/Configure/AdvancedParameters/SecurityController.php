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

namespace PrestaShopBundle\Controller\Admin\Configure\AdvancedParameters;

use Exception;
use PrestaShop\PrestaShop\Core\Domain\Security\Command\BulkDeleteCustomersSessionsCommand;
use PrestaShop\PrestaShop\Core\Domain\Security\Command\DeleteCustomerSessionCommand;
use PrestaShop\PrestaShop\Core\Domain\Security\Command\DeleteEmployeeSessionCommand;
use PrestaShop\PrestaShop\Core\Domain\Session\Exception\SessionException;
use PrestaShop\PrestaShop\Core\Domain\Session\Exception\SessionNotFoundException;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Search\Filters\Security\Sessions\CustomerFilters;
use PrestaShop\PrestaShop\Core\Search\Filters\Security\Sessions\EmployeeFilters;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SecurityController is responsible for displaying the
 * "Configure > Advanced parameters > Team > Sessions" page.
 */
class SecurityController extends FrameworkBundleAdminController
{
    /**
     * Show sessions listing page.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @return Response
     */
    public function indexAction()
    {
        $generalForm = $this->getGeneralFormHandler()->getForm();

        return $this->render('@PrestaShop/Admin/Configure/AdvancedParameters/Security/index.html.twig', [
            'layoutHeaderToolbarBtn' => [],
            'layoutTitle' => $this->trans('Security', 'Admin.Navigation.Menu'),
            'generalForm' => $generalForm->createView(),
        ]);
    }

    /**
     * Process the Security general configuration form.
     *
     * @AdminSecurity("is_granted(['update', 'create', 'delete'], request.get('_legacy_controller'))")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function processGeneralFormAction(Request $request)
    {
        return $this->processForm(
            $request,
            $this->getGeneralFormHandler(),
            'General'
        );
    }

    /**
     * Process the Security configuration form.
     *
     * @param Request $request
     * @param FormHandlerInterface $formHandler
     * @param string $hookName
     *
     * @return RedirectResponse
     */
    protected function processForm(Request $request, FormHandlerInterface $formHandler, string $hookName)
    {
        $this->dispatchHook(
            'actionAdminSecurityControllerPostProcess' . $hookName . 'Before',
            ['controller' => $this]
        );

        $this->dispatchHook('actionAdminSecurityControllerPostProcessBefore', ['controller' => $this]);

        $form = $formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $saveErrors = $formHandler->save($data);

            if (0 === count($saveErrors)) {
                $this->addFlash('success', $this->trans('Update successful', 'Admin.Notifications.Success'));
            } else {
                $this->flashErrors($saveErrors);
            }
        }

        return $this->redirectToRoute('admin_security');
    }

    /**
     * Show Employees sessions listing page.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param EmployeeFilters $filters
     *
     * @return Response
     */
    public function employeesSessionsAction(EmployeeFilters $filters)
    {
        $sessionsEmployeesGridFactory = $this->get('prestashop.core.grid.factory.security.sessions.employees');

        return $this->render(
            '@PrestaShop/Admin/Configure/AdvancedParameters/Security/employees.html.twig',
            [
                'enableSidebar' => true,
                'layoutTitle' => $this->trans('Employees Sessions', 'Admin.Navigation.Menu'),
                'grid' => $this->presentGrid($sessionsEmployeesGridFactory->getGrid($filters)),
            ]
        );
    }

    /**
     * Show Customers sessions listing page.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param CustomerFilters $filters
     *
     * @return Response
     */
    public function customersSessionsAction(CustomerFilters $filters)
    {
        $sessionsCustomersGridFactory = $this->get('prestashop.core.grid.factory.security.sessions.customers');

        return $this->render(
            '@PrestaShop/Admin/Configure/AdvancedParameters/Security/customers.html.twig',
            [
                'enableSidebar' => true,
                'layoutTitle' => $this->trans('Employees Sessions', 'Admin.Navigation.Menu'),
                'grid' => $this->presentGrid($sessionsCustomersGridFactory->getGrid($filters)),
            ]
        );
    }

    /**
     * Delete an employee session.
     *
     * @AdminSecurity(
     *     "is_granted('delete', request.get('_legacy_controller')~'_')",
     *     message="You do not have permission to edit this."
     * )
     *
     * @param int $sessionId
     *
     * @return RedirectResponse
     */
    public function deleteEmployeeSessionAction(int $sessionId)
    {
        try {
            $deleteSessionCommand = new DeleteEmployeeSessionCommand($sessionId);

            $this->getCommandBus()->handle($deleteSessionCommand);

            $this->addFlash('success', $this->trans('Successful deletion', 'Admin.Notifications.Success'));
        } catch (SessionException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages()));
        }

        return $this->redirectToRoute('admin_security_sessions_employees');
    }

    /**
     * Delete a customer session.
     *
     * @AdminSecurity(
     *     "is_granted('delete', request.get('_legacy_controller')~'_')",
     *     message="You do not have permission to edit this."
     * )
     *
     * @param int $sessionId
     *
     * @return RedirectResponse
     */
    public function deleteCustomerSessionAction(int $sessionId)
    {
        try {
            $deleteSessionCommand = new DeleteCustomerSessionCommand($sessionId);

            $this->getCommandBus()->handle($deleteSessionCommand);

            $this->addFlash('success', $this->trans('Successful deletion', 'Admin.Notifications.Success'));
        } catch (SessionException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages()));
        }

        return $this->redirectToRoute('admin_security_sessions_customers');
    }

    /**
     * Bulk delete customers sessions.
     *
     * @AdminSecurity(
     *     "is_granted('delete', request.get('_legacy_controller')~'_')",
     *     message="You do not have permission to edit this."
     * )
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkDeleteCustomersSessionsAction(Request $request)
    {
        $sessionIds = $request->request->get('security_sessions_customers_bulk');

        try {
            $deleteSessionsCommand = new BulkDeleteCustomersSessionsCommand($sessionIds);

            $this->getCommandBus()->handle($deleteSessionsCommand);

            $this->addFlash('success', $this->trans('Successful deletion', 'Admin.Notifications.Success'));
        } catch (SessionException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages()));
        }

        return $this->redirectToRoute('admin_security_sessions_customers');
    }

    /**
     * Get human readable error for exception.
     *
     * @return array
     */
    protected function getErrorMessages()
    {
        return [
            SessionNotFoundException::class => $this->trans(
                'The object cannot be loaded (or found)',
                'Admin.Notifications.Error'
            ),
        ];
    }

    /**
     * @return FormHandlerInterface
     */
    protected function getGeneralFormHandler(): FormHandlerInterface
    {
        return $this->get('prestashop.adapter.security.general.form_handler');
    }
}

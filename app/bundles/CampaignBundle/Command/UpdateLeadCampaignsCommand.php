<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateLeadCampaignsCommand extends ModeratedCommand
{
    protected function configure()
    {
        $this
            ->setName('mautic:campaigns:update')
            ->setAliases(
                array(
                    'mautic:update:campaigns',
                    'mautic:rebuild:campaigns',
                    'mautic:campaigns:rebuild',
                )
            )
            ->setDescription('Rebuild campaigns based on lead lists.')
            ->addOption('--batch-limit', '-l', InputOption::VALUE_OPTIONAL, 'Set batch size of leads to process per round. Defaults to 300.', 300)
            ->addOption(
                '--max-leads',
                '-m',
                InputOption::VALUE_OPTIONAL,
                'Set max number of leads to process per campaign for this script execution. Defaults to all.',
                false
            )
            ->addOption('--campaign-id', '-i', InputOption::VALUE_OPTIONAL, 'Specific ID to rebuild. Defaults to all.', false)
            ->addOption('--force', '-f', InputOption::VALUE_NONE, 'Force execution even if another process is assumed running.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container  = $this->getContainer();
        $factory    = $container->get('mautic.factory');
        $translator = $factory->getTranslator();
        $em         = $factory->getEntityManager();

        /** @var \Mautic\CampaignBundle\Model\CampaignModel $campaignModel */
        $campaignModel = $factory->getModel('campaign');

        $id    = $input->getOption('campaign-id');
        $batch = $input->getOption('batch-limit');
        $max   = $input->getOption('max-leads');

        if (!$this->checkRunStatus($input, $output, ($id) ? $id : 'all')) {

            return 0;
        }

        if ($id) {
            $campaign = $campaignModel->getEntity($id);
            if ($campaign !== null) {
                $output->writeln('<info>'.$translator->trans('mautic.campaign.rebuild.rebuilding', array('%id%' => $id)).'</info>');
                $processed = $campaignModel->rebuildCampaignLeads($campaign, $batch, $max, $output);
                $output->writeln(
                    '<comment>'.$translator->trans('mautic.campaign.rebuild.leads_affected', array('%leads%' => $processed)).'</comment>'."\n"
                );
            } else {
                $output->writeln('<error>'.$translator->trans('mautic.campaign.rebuild.not_found', array('%id%' => $id)).'</error>');
            }
        } else {
            $campaigns = $campaignModel->getEntities(
                array(
                    'iterator_mode' => true
                )
            );

            while (($c = $campaigns->next()) !== false) {
                // Get first item; using reset as the key will be the ID and not 0
                $c = reset($c);

                if ($c->isPublished()) {
                    $output->writeln('<info>'.$translator->trans('mautic.campaign.rebuild.rebuilding', array('%id%' => $c->getId())).'</info>');

                    $processed = $campaignModel->rebuildCampaignLeads($c, $batch, $max, $output);
                    $output->writeln(
                        '<comment>'.$translator->trans('mautic.campaign.rebuild.leads_affected', array('%leads%' => $processed)).'</comment>'."\n"
                    );
                }

                $em->detach($c);
                unset($c);
            }

            unset($campaigns);
        }

        $this->completeRun();

        return 0;
    }
}
<?php

/**
 * DokuWiki Action component of DiffPreview Plugin
 *
 * @license  GPL-2.0-or-later (http://www.gnu.org/licenses/gpl.html)
 * @author   Mikhail I. Izmestev <izmmishao5@gmail.com>
 * @author   Tilwa Qendov <tilwa.qendov@gmail.com>
 * @version  1.1.2
 */
class action_plugin_diffpreview extends DokuWiki_Action_Plugin
{
    /**
     * Register its handlers with the DokuWiki's event controller
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, '_edit_form');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_action_act_preprocess');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, '_tpl_act_changes');
    }

    /**
     * Add "Changes" button to the edit form
     */
    public function _edit_form(Doku_Event $event, $param)
    {
        $preview = $event->data->findElementById('edbtn__preview');
        if ($preview !== false) {
            $event->data->insertElement($preview+1,
                form_makeButton('submit', 'changes', $this->getLang('changes'),
                    array('id' => 'edbtn__changes', 'accesskey' => 'c', 'tabindex' => '5')));
        }
    }

    /**
     * Process the "changes" action
     */
    public function _action_act_preprocess(Doku_Event $event, $param)
    {
        global $ACT, $INFO;

        $action =& $event->data;

        if (!( /* Valid cases */
            $action == 'changes' // Greebo
            // Frusterick Manners and below... probably
            || is_array($action) && array_key_exists('changes', $action)
        )) return;

        /* We check the DokuWiki release */
        if (class_exists('\\dokuwiki\\ActionRouter', false)) {
            /* release Greebo and above */

            /* See ActionRouter->setupAction() and Action\Preview */
            // WARN: Only works because Action\Edit methods are public
            $ae = new dokuwiki\Action\Edit();
            $ae->checkPreconditions();
            $this->savedraft();
            $ae->preProcess();

            $event->stopPropagation();
            $event->preventDefault();

        } elseif (function_exists('act_permcheck')) {
            /* Release Frusterick Manners and below */

            // Same setup as preview: permissions and environment
            if ('preview' == act_permcheck('preview')
                && 'preview' == act_edit('preview'))
            {
                act_draftsave('preview');
                $ACT = 'changes';

                $event->stopPropagation();
                $event->preventDefault();
            } else {
                $ACT = 'preview';
            }

        } else {
            // Fallback
            $ACT = 'preview';
        }
    }

    /**
     * Display the "changes" page
     */
    public function _tpl_act_changes(Doku_Event $event, $param)
    {
        global $TEXT;
        global $PRE;
        global $SUF;

        if ('changes' != $event->data) return;

        html_edit($TEXT);
        echo '<br id="scroll__here" />';
        html_diff(con($PRE,$TEXT,$SUF));

        $event->preventDefault();
    }

    /**
     * Saves a draft on show changes
     * Returns if the permissions don't allow it
     *
     * Copied from dokuwiki\Action\Preview (inc/Action/Preview.php)
     * so that we use the same draft. The two different versions come from
     * different releases.
     */
    protected function savedraft()
    {
        global $INFO, $ID, $INPUT, $conf;

        if (class_exists('\\dokuwiki\\Draft', false)) {
            /* Release Hogfather (and above) */

            $draft = new \dokuwiki\Draft($ID, $INFO['client']);
            if (!$draft->saveDraft()) {
                $errors = $draft->getErrors();
                foreach ($errors as $error) {
                    msg(hsc($error), -1);
                }
            }

        } else {
            /* Release Greebo and below */

            if (!$conf['usedraft']) return;
            if (!$INPUT->post->has('wikitext')) return;

            // ensure environment (safeguard when used via AJAX)
            assert(isset($INFO['client']), 'INFO.client should have been set');
            assert(isset($ID), 'ID should have been set');

            $draft = array(
                'id' => $ID,
                'prefix' => substr($INPUT->post->str('prefix'), 0, -1),
                'text' => $INPUT->post->str('wikitext'),
                'suffix' => $INPUT->post->str('suffix'),
                'date' => $INPUT->post->int('date'),
                'client' => $INFO['client'],
            );
            $cname = getCacheName($draft['client'] . $ID, '.draft');
            if (io_saveFile($cname, serialize($draft))) {
                $INFO['draft'] = $cname;
            }
        }
    }
}

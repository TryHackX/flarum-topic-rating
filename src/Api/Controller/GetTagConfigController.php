<?php

namespace TryHackX\TopicRating\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin-only endpoint that returns the full Topic Rating configuration as
 * structured JSON (already parsed — no raw setting strings to decode on the
 * client). Intended for automation: migration scripts, audit tools, external
 * dashboards, etc.
 *
 * Response shape:
 * {
 *   "settings": {
 *     "enabled": bool,
 *     "showOnList": bool,
 *     "rateOnList": bool,
 *     "allowUnactivated": bool,
 *     "displayWhenRestricted": "readonly" | "hidden" | "message"
 *   },
 *   "bypassGroups": ["1", "4", ...],
 *   "tagConfig": {
 *     "5": {"state": "disabled"},
 *     "5_7": {"state": "groups", "groups": ["3"]},
 *     ...
 *   }
 * }
 */
class GetTagConfigController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $tagConfigRaw = (string) $this->settings->get('tryhackx-topic-rating.tag_config', '{}');
        $bypassGroupsRaw = (string) $this->settings->get('tryhackx-topic-rating.bypass_groups', '["1"]');

        $tagConfig = json_decode($tagConfigRaw, true);
        if (! is_array($tagConfig)) {
            $tagConfig = [];
        }

        $bypassGroups = json_decode($bypassGroupsRaw, true);
        $bypassGroups = is_array($bypassGroups) ? array_map('strval', $bypassGroups) : [];

        $mode = (string) $this->settings->get('tryhackx-topic-rating.display_when_restricted', 'readonly');
        if (! in_array($mode, ['readonly', 'hidden', 'message'], true)) {
            $mode = 'readonly';
        }

        return new JsonResponse([
            'settings' => [
                'enabled'               => (bool) $this->settings->get('tryhackx-topic-rating.enabled', true),
                'showOnList'            => (bool) $this->settings->get('tryhackx-topic-rating.show_on_list', true),
                'rateOnList'            => (bool) $this->settings->get('tryhackx-topic-rating.rate_on_list', true),
                'allowUnactivated'      => (bool) $this->settings->get('tryhackx-topic-rating.allow_unactivated', false),
                'displayWhenRestricted' => $mode,
            ],
            'bypassGroups' => $bypassGroups,
            'tagConfig'    => (object) $tagConfig,
        ]);
    }
}

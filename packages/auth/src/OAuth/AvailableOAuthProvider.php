<?php

namespace Tempest\Auth\OAuth;

enum AvailableOAuthProvider: string
{
    case APPLE = 'apple';
    case DISCORD = 'discord';
    case FACEBOOK = 'facebook';
    case GENERIC = 'generic';
    case GITHUB = 'github';
    case GOOGLE = 'google';
    case INSTAGRAM = 'instagram';
    case LINKEDIN = 'linkedin';
    case MICROSOFT = 'microsoft';
    case SLACK = 'slack';
}

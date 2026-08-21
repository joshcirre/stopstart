<?php

/*
 * All realtime traffic uses public channels named "project.{remote_token}".
 * The 40-character random token is the capability: only devices that were
 * given the remote URL (or own the project) know the channel name, and the
 * app has no auth guard to back private-channel authorization.
 */

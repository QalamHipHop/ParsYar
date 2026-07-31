/**
 * ParsYar Enterprise Mobile — entry point
 * @format
 */

import { AppRegistry } from 'react-native';
import App from './src/App';
import { name as appName } from './app.json';
import { headlessPushTask } from './src/lib/push';

// Headless task: when a push arrives while the app is killed, iOS/Android
// spin up a JS VM and call this. It runs WITHOUT a UI; we only persist the
// payload so the next foreground launch can surface it as a deep link.
AppRegistry.registerHeadlessTask('RNPushNotificationHandleBackgroundAction', () => headlessPushTask);

AppRegistry.registerComponent(appName, () => App);

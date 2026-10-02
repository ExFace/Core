We need users to get push notification on desktop and mobile browsers when they receive an in-app notification on the workbench. 

In-app notifications already exist. They are managed by the `NotificationContext`. They can be sent like any other communication message by the `NotifyingBehavior` or the `SenfMessage` action if they target the `exface.Core.NOTIFICATIONS` channel. 

## Use cases

- Get push message when an in-app notification is received by my user: e.g.
	- When a ticket is assigned to me
- Get push message triggered by a `NotifyingBehavior` directly

## New Web push channel

The idea is to add another channel for web-push notifications (`exface.Core.WEB_PUSH`) and to send such push messages automatically when an in-app notification is received. The separate channel will also allow sending messages via web-push directly without the in-app notification. In this case, the message would not be saved in the workbench notification storage, which may be beneficial in some use-cases. 

Push messages must be sent from PHP ad they will get rendered there. 

## Push messages

We will need a new message prototype `WebPushMessage`. Propose a good structure for it. 

Push messages must support recipients similarly to `EmailMessage`:

- `recipient_users`
- `recipient_roles` - all users with this role will receive the message

In the end, a push message always targets a user (similar to in-app notifications). It then gets pushed to all devices the user has registered.

## Subscribing for push messages

The user must subscribe for push messages from a specific device. There should be an push-message menu item in the notification context menu. The user must be able to subscribe by clicking this menu item any time - ideally from any UI facade (but definitely from UI5Facade and jEasyUI). 

### WebPushFacade

The overall idea was to provide a separate HTTP facade to manage push subscriptions. That facade would provide web services, that UI facades can use. 

Ideally, a UI facade would simply need to include a JS file URL pointing to `api/webpush/manager.js` and that JS file would take care of everything a UI facade needs. So there should be minimum code in the UI facades. 

### Subscriptions per user

As far as I understand, every device a user wants to receive the push notifications (or every browser?) must be registered separately. We need to have a list of these registrations visible in the user administration. We need to see, when a registration expired (e.g. because the device was not available of its settings changed), when it was active last time, etc. Admins should be able to remove these registrations. Perhaps users too?
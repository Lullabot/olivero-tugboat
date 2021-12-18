/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(($, {
  behaviors
}, {
  testMessages
}) => {
  const indexes = {};
  testMessages.types.forEach(type => {
    indexes[type] = [];
  });
  const messageObjects = {
    default: {
      zone: new Drupal.Message(),
      indexes
    },
    multiple: []
  };
  messageObjects.default.zone.clear();
  testMessages.selectors.filter(Boolean).forEach(selector => {
    messageObjects[selector] = {
      zone: new Drupal.Message(document.querySelector(selector)),
      indexes
    };
  });
  behaviors.js_message_test = {
    attach() {
      $(once('messages-details', '[data-drupal-messages-area]')).on('click', '[data-action]', e => {
        const $target = $(e.currentTarget);
        const type = $target.attr('data-type');
        const area = $target.closest('[data-drupal-messages-area]').attr('data-drupal-messages-area') || 'default';
        const message = messageObjects[area].zone;
        const action = $target.attr('data-action');

        if (action === 'add') {
          messageObjects[area].indexes[type].push(message.add(`This is a message of the type, ${type}. You be the judge of its importance.`, {
            type
          }));
        } else if (action === 'remove') {
          message.remove(messageObjects[area].indexes[type].pop());
        }
      });
      $(once('add-multiple', '[data-action="add-multiple"]')).on('click', () => {
        [0, 1, 2, 3, 4, 5].forEach(i => {
          messageObjects.multiple.push(messageObjects.default.zone.add(`This is message number ${i} of the type, ${testMessages.types[i % testMessages.types.length]}. You be the judge of its importance.`, {
            type: testMessages.types[i % testMessages.types.length]
          }));
        });
      });
      $(once('remove-multiple', '[data-action="remove-multiple"]')).on('click', () => {
        messageObjects.multiple.forEach(messageIndex => messageObjects.default.zone.remove(messageIndex));
        messageObjects.multiple = [];
      });
      $(once('add-multiple-error', '[data-action="add-multiple-error"]')).on('click', () => {
        [0, 1, 2, 3, 4, 5].forEach(i => messageObjects.default.zone.add(`Msg-${i}`, {
          type: 'error'
        }));
        messageObjects.default.zone.add(`Msg-${testMessages.types.length * 2}`, {
          type: 'status'
        });
      });
      $(once('remove-type', '[data-action="remove-type"]')).on('click', () => {
        Array.prototype.map.call(document.querySelectorAll('[data-drupal-message-id^="error"]'), element => element.getAttribute('data-drupal-message-id')).forEach(id => messageObjects.default.zone.remove(id));
      });
      $(once('clear-all', '[data-action="clear-all"]')).on('click', () => {
        messageObjects.default.zone.clear();
      });
      $(once('id-no-status', '[data-action="id-no-status"]')).on('click', () => {
        messageObjects.default.zone.add('Msg-id-no-status', {
          id: 'my-special-id'
        });
      });
    }

  };
})(jQuery, Drupal, drupalSettings);
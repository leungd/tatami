// Loaded unbuilt by block.json, so it uses the editor's `wp` globals rather than imports.
// Registers both blocks: tatami/faqs (the list) and tatami/faq (one question).
(({ blocks, blockEditor, components, data, element, i18n }) => {
  const el = element.createElement;
  const { __ } = i18n;
  const {
    BlockControls,
    InnerBlocks,
    RichText,
    useBlockProps,
    useInnerBlocksProps,
  } = blockEditor;

  const addQuestionButton = (onClick) =>
    el(
      BlockControls,
      { group: 'other' },
      el(components.ToolbarButton, { onClick }, __('Add Question', 'tatami')),
    );

  blocks.registerBlockType('tatami/faqs', {
    edit: ({ clientId }) => {
      const { insertBlock } = data.useDispatch('core/block-editor');
      const count = data.useSelect(
        (select) => select('core/block-editor').getBlockCount(clientId),
        [clientId],
      );

      return el(
        element.Fragment,
        null,
        addQuestionButton(() =>
          insertBlock(blocks.createBlock('tatami/faq'), count, clientId),
        ),
        el(
          'div',
          useInnerBlocksProps(useBlockProps(), {
            allowedBlocks: ['tatami/faq'],
            template: [['tatami/faq']],
            renderAppender: () =>
              el(
                components.Button,
                {
                  variant: 'secondary',
                  icon: 'plus',
                  className: 'tatami-faqs__add',
                  onClick: () =>
                    insertBlock(
                      blocks.createBlock('tatami/faq'),
                      count,
                      clientId,
                    ),
                },
                __('Add Question', 'tatami'),
              ),
          }),
        ),
      );
    },
    save: () => el(InnerBlocks.Content),
  });

  blocks.registerBlockType('tatami/faq', {
    edit: ({ attributes, setAttributes, clientId }) => {
      const { insertBlock, selectBlock } =
        data.useDispatch('core/block-editor');
      const { listId, index, firstAnswerBlock } = data.useSelect(
        (select) => {
          const editor = select('core/block-editor');
          return {
            listId: editor.getBlockRootClientId(clientId),
            index: editor.getBlockIndex(clientId),
            firstAnswerBlock: editor.getBlockOrder(clientId)[0],
          };
        },
        [clientId],
      );
      const answerProps = useInnerBlocksProps(
        { className: 'tatami-faq__answer' },
        {
          allowedBlocks: ['core/paragraph', 'core/list'],
          template: [
            [
              'core/paragraph',
              { placeholder: __('Write the answer', 'tatami') },
            ],
          ],
        },
      );

      return el(
        element.Fragment,
        null,
        addQuestionButton(() =>
          insertBlock(blocks.createBlock('tatami/faq'), index + 1, listId),
        ),
        el(
          'div',
          useBlockProps(),
          el(
            'span',
            { className: 'tatami-faq__label', 'aria-hidden': true },
            __('Question', 'tatami'),
          ),
          el(
            'div',
            {
              className: 'tatami-faq__question',
              onKeyDown: (event) => {
                if (event.key === 'Enter' && firstAnswerBlock) {
                  event.preventDefault();
                  selectBlock(firstAnswerBlock, 0);
                }
              },
            },
            el(RichText, {
              tagName: 'p',
              'aria-label': __('Question', 'tatami'),
              placeholder: __('Write the question', 'tatami'),
              value: attributes.question,
              onChange: (question) => setAttributes({ question }),
              allowedFormats: [],
              disableLineBreaks: true,
            }),
          ),
          el(
            'span',
            { className: 'tatami-faq__label', 'aria-hidden': true },
            __('Answer', 'tatami'),
          ),
          el('div', answerProps),
        ),
      );
    },
    save: () => el(InnerBlocks.Content),
  });
})(window.wp);

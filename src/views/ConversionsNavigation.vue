<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
<NcAppNavigation :aria-label="t('video_converter_fm', 'Conversions')">
<template #list>
<NcAppNavigationItem
:to="{ path: '/conversions' }"
:name="t('video_converter_fm', 'Conversions en cours')"
:aria-label="t('video_converter_fm', 'Conversions en cours')">
<template #icon>
<NcIconSvgWrapper :svg="convertIcon" />
</template>
</NcAppNavigationItem>

<NcAppNavigationItem
:to="{ path: '/settings' }"
:name="t('video_converter_fm', 'Paramètres')"
:aria-label="t('video_converter_fm', 'Paramètres')"
icon="icon-settings">
</NcAppNavigationItem>
</template>
</NcAppNavigation>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { NcAppNavigation, NcAppNavigationItem, NcIconSvgWrapper } from '@nextcloud/vue'
import convertIcon from '../../img/convert_icon.svg?raw'
</script>

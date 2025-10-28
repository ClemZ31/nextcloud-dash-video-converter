<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="video_converter_fm">
		<router-view name="navigation" />
		<router-view />
	</NcContent>
</template>

<script setup>
import { NcContent } from '@nextcloud/vue'
</script>
